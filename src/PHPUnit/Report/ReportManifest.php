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

        $tests = [];
        foreach ($recordings as $index => $recording) {
            $totalSteps = self::totalSteps($recording->moments);
            $tests[] = [
                'id' => $recording->testId,
                'title' => $recording->title,
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
