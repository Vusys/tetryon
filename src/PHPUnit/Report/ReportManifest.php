<?php

declare(strict_types=1);

namespace Vusys\Tetryon\PHPUnit\Report;

use Vusys\Tetryon\Core\Diagnostics\FailureReport;
use Vusys\Tetryon\Core\Selector\ResolutionAttempt;

/**
 * Turns a list of {@see TestRecording}s, plus the screenshot/diagnostics
 * paths already written for them, into the plain-array structure inlined
 * into the report's `index.html`. Pure — no I/O — so it's directly
 * unit-testable independent of {@see ReportRenderer}'s file-writing.
 */
final class ReportManifest
{
    /**
     * @param  list<TestRecording>  $recordings
     * @param  list<list<string>>  $screenshotPaths  one relative path per moment, indexed like $recordings[$i]->moments
     * @param  list<string|null>  $diagnosticsDirs  one relative diagnostics directory per recording, or null when it passed
     * @param  list<string|null>  $diagnosticsReports  the rendered {@see FailureReport} text per recording, or null when it passed
     * @return array<string, mixed>
     */
    public static function build(array $recordings, array $screenshotPaths, array $diagnosticsDirs, array $diagnosticsReports = []): array
    {
        $total = count($recordings);
        $passed = count(array_filter($recordings, static fn (TestRecording $recording): bool => $recording->passed));

        $groups = self::groups(array_map(static fn (TestRecording $recording): string => $recording->testId, $recordings));

        $tests = [];
        foreach ($recordings as $index => $recording) {
            $totalSteps = self::totalSteps($recording->moments);
            $tests[] = [
                'id' => $recording->testId,
                'title' => $recording->title,
                'group' => $groups[$index],
                'totalSteps' => $totalSteps,
                'passed' => $recording->passed,
                'index' => $index + 1,
                'total' => $total,
                'diagnosticsDir' => $diagnosticsDirs[$index] ?? null,
                'diagnosticsReport' => $diagnosticsReports[$index] ?? null,
                'moments' => self::moments($recording, $totalSteps, $screenshotPaths[$index] ?? []),
            ];
        }

        return [
            'summary' => ['total' => $total, 'passed' => $passed, 'failed' => $total - $passed],
            'tests' => $tests,
        ];
    }

    /**
     * One manifest from the test entries of several {@see build()} calls, such
     * as each parallel worker's part (#193): renumbered, regrouped against the
     * whole suite, and summarised again.
     *
     * @param  list<array<string, mixed>>  $tests
     * @return array{summary: array{total: int, passed: int, failed: int}, tests: list<array<string, mixed>>}
     */
    public static function combine(array $tests): array
    {
        $total = count($tests);
        $passed = count(array_filter($tests, static fn (array $test): bool => ($test['passed'] ?? false) === true));
        $groups = self::groups(array_map(static fn (array $test): string => is_string($test['id'] ?? null) ? $test['id'] : '', $tests));

        foreach ($tests as $index => $test) {
            $tests[$index]['group'] = $groups[$index];
            $tests[$index]['index'] = $index + 1;
            $tests[$index]['total'] = $total;
        }

        return [
            'summary' => ['total' => $total, 'passed' => $passed, 'failed' => $total - $passed],
            'tests' => $tests,
        ];
    }

    /**
     * Where each test sits in the sidebar: the folder its class lives in,
     * below the namespace every test shares, then the class as a phrase —
     * `Tests\Browser\DataSets\DataSetShowTest::test_x` → `['Data sets', 'Data set show']`
     * when every test is under `Tests\Browser`. A test id that isn't
     * `Class::method` gets no group.
     *
     * @param  list<string>  $testIds
     * @return list<list<string>>
     */
    private static function groups(array $testIds): array
    {
        $namespaces = [];
        foreach ($testIds as $index => $testId) {
            $class = strstr($testId, '::', true);
            if ($class !== false) {
                $namespaces[$index] = explode('\\', $class);
            }
        }

        // The class itself is never part of the shared prefix.
        $shared = $namespaces === [] ? 0 : min(array_map(static fn (array $parts): int => count($parts) - 1, $namespaces));
        $first = $namespaces === [] ? [] : reset($namespaces);
        foreach ($namespaces as $parts) {
            $depth = 0;
            while ($depth < $shared && $parts[$depth] === $first[$depth]) {
                $depth++;
            }
            $shared = $depth;
        }

        $groups = [];
        foreach ($testIds as $index => $testId) {
            if (! isset($namespaces[$index])) {
                $groups[] = [];

                continue;
            }
            $parts = $namespaces[$index];
            $class = array_pop($parts);
            $folder = implode(' / ', array_map(self::phrase(...), array_slice($parts, $shared)));
            $phrase = self::phrase(preg_replace('/Test$/', '', $class) ?: $class);
            $groups[] = $folder === '' ? [$phrase] : [$folder, $phrase];
        }

        return $groups;
    }

    /** `DataSets` → `Data sets`. */
    private static function phrase(string $name): string
    {
        $words = preg_split('/(?<=[a-z0-9])(?=[A-Z])/', $name) ?: [$name];

        return ucfirst(strtolower(implode(' ', $words)));
    }

    /**
     * The declared step count is derived from the moments actually captured
     * — the beat index of the last one — rather than trusted from the
     * developer, so the report can never claim a count the recording didn't
     * produce.
     *
     * @param  list<Moment>  $moments
     */
    private static function totalSteps(array $moments): int
    {
        if ($moments === []) {
            return 0;
        }

        return max(array_map(static fn (Moment $moment): int => $moment->stepIndex, $moments));
    }

    /**
     * @param  list<string>  $paths
     * @return list<array<string, mixed>>
     */
    private static function moments(TestRecording $recording, int $totalSteps, array $paths): array
    {
        $moments = [];
        foreach ($recording->moments as $index => $moment) {
            $moments[] = [
                'src' => $paths[$index] ?? null,
                'caption' => $moment->caption,
                'stepIndex' => $moment->stepIndex,
                'totalSteps' => $totalSteps,
                'progress' => $moment->progress,
                'durationMs' => $moment->durationMs,
                'outcome' => $moment->outcome,
                'verified' => $moment->verified,
                'assertions' => $moment->assertions,
                'atMs' => $moment->atMs,
                'overlay' => $moment->overlay,
                'key' => $moment->key,
                'selectorTrace' => $moment->selectorFailure === null ? null : [
                    'target' => $moment->selectorFailure->target,
                    'attempts' => array_map(
                        static fn (ResolutionAttempt $attempt): array => [
                            'description' => $attempt->description,
                            'matchCount' => $attempt->matchCount,
                        ],
                        $moment->selectorFailure->attempts,
                    ),
                ],
            ];
        }

        return $moments;
    }
}
