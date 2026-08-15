<?php

declare(strict_types=1);

namespace Vusys\Tetryon\PHPUnit\Recording;

use InvalidArgumentException;
use Vusys\Tetryon\PHPUnit\Recording\Exception\RecordingException;

/**
 * Turns one {@see Slide} into a composited PNG: a header bar (title left,
 * step count right), the screenshot with a border, a caption, and a chaptered
 * progress timeline. ImageMagick (`magick`) owns all text/layout so ffmpeg
 * only ever has to concatenate plain images — no `drawtext`/`libfreetype`
 * dependency (see issue #102).
 */
final readonly class SlideCompositor
{
    private const int BORDER = 4;

    private const int PADDING = 16;

    private const int HEADER_HEIGHT = 56;

    private const int CAPTION_HEIGHT = 64;

    private const int TIMELINE_HEIGHT = 40;

    private const int TIMELINE_PADDING = 20;

    private const int TIMELINE_TRACK_HEIGHT = 12;

    private const int TIMELINE_GAP = 6;

    private const string PAPER = '#f8fafc';

    private const string INK = '#0f172a';

    private const string ACCENT = '#3b82f6';

    private const string ACCENT_LIGHT = '#93c5fd';

    private const string TRACK = '#e2e8f0';

    private const string FONT = 'DejaVu-Sans';

    private const string FONT_BOLD = 'DejaVu-Sans-Bold';

    public function __construct(
        private string $magickBinary,
        private string $title,
        private int $totalSteps,
    ) {
        if ($totalSteps < 1) {
            throw new InvalidArgumentException('SlideCompositor needs at least one total step to draw a timeline.');
        }
    }

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

        $canvasWidth = $width + self::PADDING * 2 + self::BORDER * 2;
        $canvasHeight = self::HEADER_HEIGHT + self::BORDER * 2 + $height + self::CAPTION_HEIGHT + self::TIMELINE_HEIGHT;

        ProcessRunner::run([
            $this->magickBinary, '-size', "{$canvasWidth}x{$canvasHeight}", 'xc:'.self::PAPER,
            '-fill', self::INK, '-draw', "rectangle 0,0 {$canvasWidth},".self::HEADER_HEIGHT,
            '-gravity', 'NorthWest', '-fill', 'white', '-pointsize', '22', '-font', self::FONT_BOLD,
            '-annotate', '+20+16', $this->title,
            '-gravity', 'NorthEast', '-fill', self::ACCENT_LIGHT, '-pointsize', '18', '-font', self::FONT,
            '-annotate', '+20+18', $slide->stepLabel,
            $borderedPath, '-gravity', 'North', '-geometry', '+0+'.self::HEADER_HEIGHT, '-composite',
            '-gravity', 'North', '-fill', self::INK, '-pointsize', '18', '-font', self::FONT,
            '-annotate', '+0+'.(self::HEADER_HEIGHT + self::BORDER * 2 + $height + 16), $slide->caption,
            '-gravity', 'NorthWest',
            ...$this->timelineDrawArguments($canvasWidth, $canvasHeight, $slide->progress),
            $outputPath,
        ]);
    }

    /**
     * @return list<string>
     */
    private function timelineDrawArguments(int $canvasWidth, int $canvasHeight, float $progress): array
    {
        $trackY = $canvasHeight - self::TIMELINE_HEIGHT + 10;
        $trackX0 = self::TIMELINE_PADDING;
        $trackX1 = $canvasWidth - self::TIMELINE_PADDING;
        $trackWidth = $trackX1 - $trackX0;
        $segmentWidth = intdiv($trackWidth - self::TIMELINE_GAP * ($this->totalSteps - 1), $this->totalSteps);

        $arguments = [];
        for ($step = 1; $step <= $this->totalSteps; $step++) {
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
                $arguments[] = self::ACCENT;
                $arguments[] = '-draw';
                $arguments[] = "roundrectangle {$x0},{$trackY} {$fx1},{$bottom} 4,4";
            }
        }

        return $arguments;
    }
}
