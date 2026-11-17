<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Laravel;

use Illuminate\Database\Connection;
use LogicException;
use PDO;

/**
 * A copy of the database taken after a reused browser's authenticate(), put
 * back before each of the class's tests (#78). The test process does both on
 * its own connection; the writes are committed, so the separately served app
 * sees the restored rows on its next request.
 *
 * - SQLite: `VACUUM INTO` a temp file, restored table by table from that file
 *   attached, so the live file is never swapped under an open connection.
 * - MySQL / MariaDB: a shadow copy of every table in the same database,
 *   restored table by table with foreign keys off, dropped after the class.
 *
 * Only table contents are restored: a table created after the checkpoint is
 * left alone, and a schema change between tests isn't undone.
 */
final readonly class DatabaseCheckpoint
{
    private const string SHADOW_PREFIX = 'tetryon_cp_';

    /**
     * @param  array<string, list<string>>  $tables  table => its insertable (non-generated) columns
     * @param  array<string, string>  $shadows  MySQL only: table => shadow table
     */
    private function __construct(
        private PDO $pdo,
        private string $driver,
        private array $tables,
        private ?string $file = null,
        private array $shadows = [],
    ) {}

    public static function capture(Connection $connection): self
    {
        $pdo = $connection->getPdo();
        // PDO's own name: MariaDB reports "mysql" here too.
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        return match ($driver) {
            'sqlite' => self::captureSqlite($pdo),
            'mysql' => self::captureMySql($pdo),
            default => throw new LogicException(sprintf(
                'Tetryon can checkpoint SQLite and MySQL/MariaDB databases, not "%s". Leave database: false and reset the data yourself.',
                is_string($driver) ? $driver : 'unknown',
            )),
        };
    }

    public function restore(): void
    {
        $this->driver === 'sqlite' ? $this->restoreSqlite() : $this->restoreMySql();
    }

    public function drop(): void
    {
        if ($this->driver === 'sqlite') {
            if ($this->file !== null) {
                @unlink($this->file);
            }

            return;
        }

        foreach ($this->shadows as $shadow) {
            $this->pdo->exec('DROP TABLE IF EXISTS '.self::mysqlName($shadow));
        }
    }

    private static function captureSqlite(PDO $pdo): self
    {
        $tables = [];
        foreach (self::column($pdo, "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name") as $table) {
            $columns = [];
            foreach (self::query($pdo, 'PRAGMA table_xinfo('.self::sqliteName($table).')')->fetchAll(PDO::FETCH_ASSOC) as $column) {
                // hidden 2 and 3 are generated columns, which can't be inserted into
                $hidden = is_array($column) ? ($column['hidden'] ?? 0) : 0;
                if (is_array($column) && in_array($hidden, [0, 1, '0', '1'], true) && is_string($column['name'] ?? null)) {
                    $columns[] = $column['name'];
                }
            }
            $tables[$table] = $columns;
        }

        $file = sys_get_temp_dir().'/tetryon-checkpoint-'.bin2hex(random_bytes(6)).'.sqlite';
        $pdo->exec('VACUUM INTO '.$pdo->quote($file));

        return new self($pdo, 'sqlite', $tables, $file);
    }

    private function restoreSqlite(): void
    {
        $pdo = $this->pdo;
        $foreignKeys = self::query($pdo, 'PRAGMA foreign_keys')->fetchColumn();
        $pdo->exec('PRAGMA foreign_keys = OFF');
        $pdo->exec('ATTACH DATABASE '.$pdo->quote((string) $this->file).' AS tetryon_checkpoint');

        try {
            $pdo->beginTransaction();
            foreach ($this->tables as $table => $columns) {
                $name = self::sqliteName($table);
                $list = implode(', ', array_map(self::sqliteName(...), $columns));
                $pdo->exec("DELETE FROM main.{$name}");
                $pdo->exec("INSERT INTO main.{$name} ({$list}) SELECT {$list} FROM tetryon_checkpoint.{$name}");
            }
            // AUTOINCREMENT counters, so ids carry on from where the checkpoint was
            if (self::column($pdo, "SELECT name FROM tetryon_checkpoint.sqlite_master WHERE name = 'sqlite_sequence'") !== []) {
                $pdo->exec('DELETE FROM main.sqlite_sequence');
                $pdo->exec('INSERT INTO main.sqlite_sequence SELECT * FROM tetryon_checkpoint.sqlite_sequence');
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        } finally {
            $pdo->exec('DETACH DATABASE tetryon_checkpoint');
            $pdo->exec('PRAGMA foreign_keys = '.(in_array($foreignKeys, [1, '1'], true) ? 'ON' : 'OFF'));
        }
    }

    private static function captureMySql(PDO $pdo): self
    {
        $tables = [];
        $shadows = [];
        $all = self::column($pdo, "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE' ORDER BY table_name");

        foreach ($all as $table) {
            if (str_starts_with($table, self::SHADOW_PREFIX)) {
                // left by a run that didn't finish
                $pdo->exec('DROP TABLE '.self::mysqlName($table));

                continue;
            }

            $statement = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND extra NOT LIKE '%GENERATED%' ORDER BY ordinal_position");
            $statement->execute([$table]);
            $tables[$table] = array_values(array_filter($statement->fetchAll(PDO::FETCH_COLUMN), is_string(...)));

            $shadow = $shadows[$table] = self::SHADOW_PREFIX.substr(md5($table), 0, 16);
            $pdo->exec('DROP TABLE IF EXISTS '.self::mysqlName($shadow));
            $pdo->exec('CREATE TABLE '.self::mysqlName($shadow).' LIKE '.self::mysqlName($table));
            $list = implode(', ', array_map(self::mysqlName(...), $tables[$table]));
            $pdo->exec('INSERT INTO '.self::mysqlName($shadow)." ({$list}) SELECT {$list} FROM ".self::mysqlName($table));
        }

        return new self($pdo, 'mysql', $tables, shadows: $shadows);
    }

    private function restoreMySql(): void
    {
        $pdo = $this->pdo;
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        try {
            foreach ($this->tables as $table => $columns) {
                $list = implode(', ', array_map(self::mysqlName(...), $columns));
                // TRUNCATE also resets AUTO_INCREMENT to follow the restored ids.
                $pdo->exec('TRUNCATE TABLE '.self::mysqlName($table));
                $pdo->exec('INSERT INTO '.self::mysqlName($table)." ({$list}) SELECT {$list} FROM ".self::mysqlName($this->shadows[$table]));
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    /**
     * @return list<string>
     */
    private static function column(PDO $pdo, string $sql): array
    {
        return array_values(array_filter(self::query($pdo, $sql)->fetchAll(PDO::FETCH_COLUMN), is_string(...)));
    }

    private static function query(PDO $pdo, string $sql): \PDOStatement
    {
        $statement = $pdo->query($sql);
        if ($statement === false) {
            throw new LogicException("Tetryon's database checkpoint query failed: {$sql}");
        }

        return $statement;
    }

    private static function sqliteName(string $name): string
    {
        return '"'.str_replace('"', '""', $name).'"';
    }

    private static function mysqlName(string $name): string
    {
        return '`'.str_replace('`', '``', $name).'`';
    }
}
