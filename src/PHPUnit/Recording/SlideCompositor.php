<?php

declare(strict_types=1);

namespace Vusys\Tetryon\PHPUnit\Recording;

use Vusys\Tetryon\PHPUnit\Recording\Exception\RecordingException;

/**
 * Turns one {@see Slide} into a composited PNG: a header bar (title left,
 * step count right), the screenshot with a border, a caption, a chaptered
 * progress timeline, and — on a test's closing frame — a pass/fail tint. A
 * slide stamped with a suite position ({@see Slide::$suiteIndex}) also gets a
 * slim overall-progress strip across the very top and a "Test N of M" line,
 * so a combined {@see SuiteRecording} shows where the whole run is, not just
 * the current test. ImageMagick (`magick`) owns all text/layout so ffmpeg
 * only ever has to concatenate plain images — no `drawtext`/`libfreetype`
 * dependency (see issue #102).
 */
final readonly class SlideCompositor
{
    private const int BORDER = 4;

    private const int PADDING = 16;

    private const int HEADER_HEIGHT = 60;

    private const int CAPTION_HEIGHT = 64;

    private const int TIMELINE_HEIGHT = 40;

    private const int TIMELINE_PADDING = 20;

    private const int TIMELINE_TRACK_HEIGHT = 12;

    private const int TIMELINE_GAP = 6;

    private const int SUITE_STRIP_HEIGHT = 8;

    private const string PAPER = '#f8fafc';

    private const string INK = '#0f172a';

    private const string ACCENT = '#3b82f6';

    private const string ACCENT_LIGHT = '#93c5fd';

    private const string TRACK = '#e2e8f0';

    private const string PASSED = '#16a34a';

    private const string FAILED = '#dc2626';

    private const string SUITE_LABEL = '#94a3b8';

    private const string FONT = 'DejaVu-Sans';

    private const string FONT_BOLD = 'DejaVu-Sans-Bold';

    public function __construct(private string $magickBinary) {}

    public function compose(Slide $slide, string $workingDirectory, string $outputPath): void
    {
        $size = getimagesizefromstring($slide->screenshotPng);
        if ($size === false) {
            throw new RecordingException('Could not read the screenshot as an image; it may be corrupt.');
        }
        [$width, $height] = $size;

        $rawPath = $workingDirectory.'/raw.png';
        file_put_contents($rawPath, $slide->screenshotPng);

        $borderedPath = $workingDirectory.'/bordered.png';
        ProcessRunner::run([
            $this->magickBinary, $rawPath, '-bordercolor', self::INK, '-border', (string) self::BORDER, $borderedPath,
        ]);

        $inSuite = $slide->suiteIndex !== null && $slide->suiteTotal !== null;
        $stripHeight = $inSuite ? self::SUITE_STRIP_HEIGHT : 0;

        $canvasWidth = $width + self::PADDING * 2 + self::BORDER * 2;
        $canvasHeight = $stripHeight + self::HEADER_HEIGHT + self::BORDER * 2 + $height + self::CAPTION_HEIGHT + self::TIMELINE_HEIGHT;

        $captionColor = match (true) {
            $slide->outcome === 'passed' => self::PASSED,
            $slide->outcome === 'failed' => self::FAILED,
            $slide->verified => self::PASSED,
            default => self::INK,
        };
        $caption = match (true) {
            $slide->outcome === 'passed' => "✓ {$slide->caption}",
            $slide->outcome === 'failed' => "✗ {$slide->caption}",
            $slide->verified => "✓ {$slide->caption}",
            default => $slide->caption,
        };

        $command = [$this->magickBinary, '-size', "{$canvasWidth}x{$canvasHeight}", 'xc:'.self::PAPER];

        if ($inSuite) {
            $command = [
                ...$command,
                ...$this->suiteStripDrawArguments($canvasWidth, $stripHeight, $slide->suiteIndex, $slide->suiteTotal, $slide->progress, $slide->totalSteps),
            ];
        }

        $command = [
            ...$command,
            '-fill', self::INK, '-draw', "rectangle 0,{$stripHeight} {$canvasWidth},".($stripHeight + self::HEADER_HEIGHT),
            '-gravity', 'NorthWest', '-fill', 'white', '-pointsize', '22', '-font', self::FONT_BOLD,
            '-annotate', '+20+'.($stripHeight + 16), $slide->title,
            '-gravity', 'NorthEast', '-fill', self::ACCENT_LIGHT, '-pointsize', '18', '-font', self::FONT,
            '-annotate', '+20+'.($stripHeight + 18), $slide->stepLabel,
        ];

        if ($inSuite) {
            $command = [
                ...$command,
                '-fill', self::SUITE_LABEL, '-pointsize', '13', '-font', self::FONT,
                '-annotate', '+20+'.($stripHeight + 40), "Test {$slide->suiteIndex} of {$slide->suiteTotal}",
            ];
        }

        $command = [
            ...$command,
            $borderedPath, '-gravity', 'North', '-geometry', '+0+'.($stripHeight + self::HEADER_HEIGHT), '-composite',
            '-gravity', 'North', '-fill', $captionColor, '-pointsize', '18', '-font', self::FONT_BOLD,
            '-annotate', '+0+'.($stripHeight + self::HEADER_HEIGHT + self::BORDER * 2 + $height + 16), $caption,
            '-gravity', 'NorthWest',
            ...$this->timelineDrawArguments($canvasWidth, $canvasHeight, $slide->progress, $slide->totalSteps, $slide->outcome),
            $outputPath,
        ];

        ProcessRunner::run($command);
    }

    /**
     * A slim bar across the very top of the frame, filled left-to-right by
     * how far the whole suite has progressed — smoothly within a test too,
     * reusing its own step-progress fraction, so the bar doesn't sit still
     * for a test's whole duration and then jump.
     *
     * @return list<string>
     */
    private function suiteStripDrawArguments(int $canvasWidth, int $stripHeight, int $suiteIndex, int $suiteTotal, float $progress, int $totalSteps): array
    {
        $localFraction = $totalSteps > 0 ? min(1.0, max(0.0, $progress / $totalSteps)) : 0.0;
        $overallFraction = min(1.0, (($suiteIndex - 1) + $localFraction) / $suiteTotal);
        $filledWidth = (int) round($canvasWidth * $overallFraction);

        $arguments = ['-fill', self::TRACK, '-draw', "rectangle 0,0 {$canvasWidth},{$stripHeight}"];
        if ($filledWidth > 0) {
            $arguments[] = '-fill';
            $arguments[] = self::ACCENT;
            $arguments[] = '-draw';
            $arguments[] = "rectangle 0,0 {$filledWidth},{$stripHeight}";
        }

        return $arguments;
    }

    /**
     * @return list<string>
     */
    private function timelineDrawArguments(int $canvasWidth, int $canvasHeight, float $progress, int $totalSteps, ?string $outcome): array
    {
        $trackY = $canvasHeight - self::TIMELINE_HEIGHT + 10;
        $trackX0 = self::TIMELINE_PADDING;
        $trackX1 = $canvasWidth - self::TIMELINE_PADDING;
        $trackWidth = $trackX1 - $trackX0;
        $segmentWidth = intdiv($trackWidth - self::TIMELINE_GAP * ($totalSteps - 1), $totalSteps);
        $fillColor = match ($outcome) {
            'passed' => self::PASSED,
            'failed' => self::FAILED,
            default => self::ACCENT,
        };

        $arguments = [];
        for ($step = 1; $step <= $totalSteps; $step++) {
            $x0 = $trackX0 + ($step - 1) * ($segmentWidth + self::TIMELINE_GAP);
            $x1 = $x0 + $segmentWidth;
            $bottom = $trackY + self::TIMELINE_TRACK_HEIGHT;

            $arguments[] = '-fill';
            $arguments[] = self::TRACK;
            $arguments[] = '-draw';
            $arguments[] = "roundrectangle {$x0},{$trackY} {$x1},{$bottom} 4,4";

            $filled = min(1.0, max(0.0, $progress - ($step - 1)));
            if ($filled <= 0.0) {
                continue;
            }

            $fx1 = (int) round($x0 + ($x1 - $x0) * $filled);
            if ($fx1 > $x0) {
                $arguments[] = '-fill';
                $arguments[] = $fillColor;
                $arguments[] = '-draw';
                $arguments[] = "roundrectangle {$x0},{$trackY} {$fx1},{$bottom} 4,4";
            }
        }

        return $arguments;
    }
}
