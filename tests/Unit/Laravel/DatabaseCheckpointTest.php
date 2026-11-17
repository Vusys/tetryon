<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\Laravel;

use Illuminate\Database\Connection;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\SQLiteConnection;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\Laravel\DatabaseCheckpoint;

/**
 * SQLite always runs. MySQL runs when TETRYON_TEST_MYSQL_DSN (with _USER and
 * _PASSWORD) points at a database the test may fill and empty, as CI's does.
 */
#[CoversClass(DatabaseCheckpoint::class)]
final class DatabaseCheckpointTest extends TestCase
{
    private ?string $sqliteFile = null;

    protected function tearDown(): void
    {
        if ($this->sqliteFile !== null) {
            @unlink($this->sqliteFile);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function engines(): iterable
    {
        yield 'sqlite' => ['sqlite'];
        yield 'mysql' => ['mysql'];
    }

    #[DataProvider('engines')]
    public function test_restore_puts_the_rows_back_and_ids_carry_on(string $engine): void
    {
        $db = $this->connection($engine);
        $db->insert('insert into users (name) values (?), (?)', ['ada', 'grace']);
        $db->insert('insert into posts (user_id, title) values (?, ?)', [1, 'hello']);

        $checkpoint = DatabaseCheckpoint::capture($db);

        $db->delete('delete from posts');
        $db->update("update users set name = 'changed' where id = 1");
        $db->insert('insert into users (name) values (?)', ['extra']);

        $checkpoint->restore();

        self::assertSame(['ada', 'grace'], array_column(array_map(static fn (object $row): array => (array) $row, $db->select('select name from users order by id')), 'name'));
        self::assertSame('hello', $db->scalar('select title from posts'));
        self::assertSame('HELLO', $db->scalar('select shout from posts'), 'the generated column still computes');

        $db->insert('insert into users (name) values (?)', ['next']);
        self::assertEquals(3, $db->scalar("select id from users where name = 'next'"), 'ids carry on from the checkpoint');

        $checkpoint->drop();
    }

    #[DataProvider('engines')]
    public function test_it_restores_again_and_again(string $engine): void
    {
        $db = $this->connection($engine);
        $db->insert('insert into users (name) values (?)', ['ada']);
        $checkpoint = DatabaseCheckpoint::capture($db);

        foreach ([1, 2, 3] as $round) {
            $db->delete('delete from users');
            $checkpoint->restore();
            self::assertEquals(1, $db->scalar('select count(*) from users'), "round {$round}");
        }

        $checkpoint->drop();
    }

    public function test_drop_removes_mysql_shadow_tables(): void
    {
        $db = $this->connection('mysql');
        $checkpoint = DatabaseCheckpoint::capture($db);
        self::assertNotSame([], $db->select("select table_name from information_schema.tables where table_schema = database() and table_name like 'tetryon_cp_%'"));

        $checkpoint->drop();

        self::assertSame([], $db->select("select table_name from information_schema.tables where table_schema = database() and table_name like 'tetryon_cp_%'"));
    }

    private function connection(string $engine): Connection
    {
        if ($engine === 'sqlite') {
            $this->sqliteFile = sys_get_temp_dir().'/tetryon-checkpoint-test-'.bin2hex(random_bytes(4)).'.sqlite';
            $db = new SQLiteConnection(new PDO('sqlite:'.$this->sqliteFile));
            $db->statement('pragma foreign_keys = on');
            $db->statement('create table users (id integer primary key autoincrement, name text not null)');
            $db->statement('create table posts (id integer primary key autoincrement, user_id integer not null references users(id), title text not null, shout text generated always as (upper(title)) virtual)');

            return $db;
        }

        $dsn = getenv('TETRYON_TEST_MYSQL_DSN');
        if (! is_string($dsn) || $dsn === '') {
            self::markTestSkipped('Set TETRYON_TEST_MYSQL_DSN (and _USER, _PASSWORD) to run the MySQL checkpoint tests.');
        }
        $pdo = new PDO($dsn, (string) getenv('TETRYON_TEST_MYSQL_USER'), (string) getenv('TETRYON_TEST_MYSQL_PASSWORD'));
        $db = new MySqlConnection($pdo);
        $db->statement('set foreign_key_checks = 0');
        $db->statement('drop table if exists posts, users');
        $db->statement('set foreign_key_checks = 1');
        $db->statement('create table users (id bigint unsigned auto_increment primary key, name varchar(50) not null)');
        $db->statement('create table posts (id bigint unsigned auto_increment primary key, user_id bigint unsigned not null, title varchar(50) not null, shout varchar(50) as (upper(title)) virtual, foreign key (user_id) references users(id))');

        return $db;
    }
}
