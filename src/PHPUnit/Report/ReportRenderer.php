<?php

declare(strict_types=1);

namespace Vusys\Tetryon\PHPUnit\Report;

use Throwable;
use Vusys\Tetryon\Core\Config\Configuration;
use Vusys\Tetryon\Core\Diagnostics\ArtifactBag;
use Vusys\Tetryon\Core\Diagnostics\FailureReport;
use Vusys\Tetryon\Core\Support\ImageEncoder;
use Vusys\Tetryon\Core\Support\Slug;
use Vusys\Tetryon\PHPUnit\FailureArtifacts;
use Vusys\Tetryon\PHPUnit\Report\Exception\ReportException;

/**
 * Renders a list of {@see TestRecording}s into a browsable HTML report: one
 * `index.html`, a `screenshots/` directory of WebP/PNG frames (one file per
 * distinct image, however many moments show it), and — for
 * failing tests — a `diagnostics/` directory of the same files
 * {@see FailureArtifacts} writes on failure. Used for the combined
 * whole-suite report ({@see SuiteReport::render()}, many recordings), though
 * nothing stops a caller from rendering a single recording on its own.
 *
 * Never throws — a bad output path or an unreadable screenshot is reported
 * through a null return, matching the rest of this feature's "recording must
 * never be why a test fails" philosophy.
 */
final class ReportRenderer
{
    /**
     * @param  list<TestRecording>  $recordings
     */
    public static function render(array $recordings, string $outputDirectory, Configuration $configuration): ?string
    {
        try {
            return self::renderOrThrow($recordings, $outputDirectory, $configuration);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    public static function renderHtml(array $manifest): string
    {
        $json = json_encode($manifest, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return str_replace('__TETRYON_REPORT_DATA__', $json, self::template());
    }

    /**
     * @param  list<TestRecording>  $recordings
     */
    private static function renderOrThrow(array $recordings, string $outputDirectory, Configuration $configuration): string
    {
        if ($recordings === []) {
            throw new ReportException('Nothing was recorded — no test called recording().');
        }

        $outputDirectory = rtrim($outputDirectory, '/');
        self::ensureDirectory("{$outputDirectory}/screenshots");

        $screenshotPaths = [];
        $diagnosticsDirs = [];
        $diagnosticsReports = [];

        foreach ($recordings as $recording) {
            $slug = Slug::forTestId($recording->testId);

            $paths = [];
            foreach ($recording->moments as $moment) {
                // Named by content: a frame the page sat on for five moments
                // — or that five tests all opened on — is written once.
                $encoded = ImageEncoder::encode($moment->screenshot);
                $relative = sprintf('screenshots/%s.%s', md5($encoded->bytes), $encoded->extension);
                if (! is_file("{$outputDirectory}/{$relative}")) {
                    file_put_contents("{$outputDirectory}/{$relative}", $encoded->bytes);
                }
                $paths[] = $relative;
            }
            $screenshotPaths[] = $paths;

            if ($recording->diagnostics instanceof ArtifactBag) {
                $relativeDir = "diagnostics/{$slug}";
                $directory = "{$outputDirectory}/{$relativeDir}";
                self::ensureDirectory($directory);
                FailureArtifacts::write($recording->diagnostics, $directory, $configuration);
                $diagnosticsDirs[] = $relativeDir;
                $diagnosticsReports[] = FailureReport::render($recording->diagnostics, self::diagnosticsPaths($recording->diagnostics, $directory));
            } else {
                $diagnosticsDirs[] = null;
                $diagnosticsReports[] = null;
            }
        }

        // Images are named by content, so a re-render into the same
        // directory would otherwise keep every frame of every earlier run.
        $current = array_flip(array_merge(...$screenshotPaths));
        foreach (glob("{$outputDirectory}/screenshots/*") ?: [] as $file) {
            if (! isset($current['screenshots/'.basename($file)])) {
                @unlink($file);
            }
        }

        $manifest = ReportManifest::build($recordings, $screenshotPaths, $diagnosticsDirs, $diagnosticsReports);

        $indexPath = "{$outputDirectory}/index.html";
        file_put_contents($indexPath, self::renderHtml($manifest));

        return $indexPath;
    }

    /**
     * @return array<string, string>
     */
    private static function diagnosticsPaths(ArtifactBag $bag, string $directory): array
    {
        $paths = [];
        if ($bag->screenshotPng !== null) {
            $paths['Screenshot'] = "{$directory}/screenshot.png";
        }
        if ($bag->html !== null) {
            $paths['HTML'] = "{$directory}/page.html";
        }
        $paths['Console'] = "{$directory}/console.log";
        $paths['Network'] = "{$directory}/network.log";
        $paths['Trace'] = "{$directory}/trace.log";

        return $paths;
    }

    private static function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! @mkdir($directory, 0o777, true) && ! is_dir($directory)) {
            throw new ReportException("Could not create the report directory \"{$directory}\".");
        }
    }

    private static function template(): string
    {
        return <<<'HTML_WRAP'
        <!doctype html>
        <html lang="en">
        <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Tetryon report</title>
        <style>
        :root {
            --paper: #f8fafc;
            --panel: #ffffff;
            --ink: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --accent: #3b82f6;
            --accent-light: #dbeafe;
            --passed: #16a34a;
            --passed-bg: #dcfce7;
            --failed: #dc2626;
            --failed-bg: #fee2e2;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --paper: #0b1220;
                --panel: #131c2e;
                --ink: #e2e8f0;
                --muted: #94a3b8;
                --border: #253044;
                --accent: #60a5fa;
                --accent-light: #1e3a5f;
                --passed: #4ade80;
                --passed-bg: #14532d;
                --failed: #f87171;
                --failed-bg: #5b1a1a;
            }
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: var(--paper);
            color: var(--ink);
            font: 15px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }
        header {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 14px 20px;
            background: var(--panel);
            border-bottom: 1px solid var(--border);
        }
        header h1 { font-size: 16px; margin: 0; }
        #summary { display: flex; align-items: center; gap: 10px; flex: 1; }
        .summary-count { font-variant-numeric: tabular-nums; color: var(--muted); font-size: 13px; white-space: nowrap; }
        .summary-bar { display: flex; gap: 2px; flex: 1; height: 8px; }
        .summary-seg { flex: 1; border-radius: 2px; background: var(--border); }
        .summary-seg.is-passed { background: var(--passed); }
        .summary-seg.is-failed { background: var(--failed); }
        #layout { display: flex; min-height: calc(100vh - 49px); }
        #sidebar { width: 280px; border-right: 1px solid var(--border); background: var(--panel); flex-shrink: 0; }
        #filter { width: 100%; padding: 10px 12px; border: none; border-bottom: 1px solid var(--border); background: transparent; color: var(--ink); font: inherit; outline: none; }
        .sidebar-list { overflow-y: auto; max-height: calc(100vh - 90px); }
        .sidebar-row {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            padding: 9px 12px;
            border: none;
            border-bottom: 1px solid var(--border);
            background: transparent;
            color: var(--ink);
            font: inherit;
            text-align: left;
            cursor: pointer;
        }
        .sidebar-row:hover { background: var(--paper); }
        .sidebar-row.is-selected { background: var(--accent-light); }
        .sidebar-row .badge { flex-shrink: 0; font-weight: 700; }
        .sidebar-row.is-passed .badge { color: var(--passed); }
        .sidebar-row.is-failed .badge { color: var(--failed); }
        .sidebar-row .row-title { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .sidebar-row .row-steps { flex-shrink: 0; color: var(--muted); font-size: 12px; }
        main { flex: 1; padding: 20px; min-width: 0; }
        #test-title { margin: 0 0 4px; font-size: 18px; }
        #test-meta { color: var(--muted); font-size: 13px; margin-bottom: 16px; }
        #stage-wrap { background: var(--panel); border: 1px solid var(--border); border-radius: 8px; overflow: hidden; }
        #stage-header { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid var(--border); }
        #caption { font-weight: 600; }
        #caption.is-passed { color: var(--passed); }
        #caption.is-failed { color: var(--failed); }
        #caption .verified-badge { color: var(--passed); margin-right: 6px; }
        #step-meta { color: var(--muted); font-size: 13px; font-variant-numeric: tabular-nums; }
        #stage { display: flex; justify-content: center; padding: 16px; background: var(--paper); }
        #stage-frame { position: relative; display: inline-block; line-height: 0; border: 1px solid var(--border); border-radius: 4px; overflow: hidden; background-size: 100% 100%; }
        #stage img { max-width: 100%; max-height: 70vh; display: block; }
        #stage img.is-fading { animation: frame-in 180ms ease-out; }
        @keyframes frame-in { from { opacity: 0; } to { opacity: 1; } }
        #overlay { position: absolute; inset: 0; pointer-events: none; line-height: 1.3; }
        .gesture-ring { position: absolute; border: 2px solid var(--accent); border-radius: 6px; box-shadow: 0 0 0 4px color-mix(in srgb, var(--accent) 30%, transparent); animation: ring-in 320ms ease-out; }
        @keyframes ring-in { from { transform: scale(1.35); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .gesture-options { position: absolute; margin: 4px 0 0; padding: 4px 0; list-style: none; min-width: 140px; max-height: 60%; overflow: hidden; background: #fff; color: #0f172a; border: 1px solid #94a3b8; border-radius: 6px; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.25); font-size: 13px; }
        .gesture-options li { padding: 3px 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .gesture-options li.is-selected { background: #3b82f6; color: #fff; }
        #nav { display: flex; align-items: center; gap: 8px; padding: 10px 16px; border-top: 1px solid var(--border); flex-wrap: wrap; }
        #nav button, #nav select {
            padding: 6px 14px;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--panel);
            color: var(--ink);
            font: inherit;
            cursor: pointer;
        }
        #nav button:disabled { opacity: 0.4; cursor: default; }
        #play { min-width: 84px; }
        #play[aria-pressed="true"] { background: var(--accent-light); border-color: var(--accent); }
        #nav-controls { display: flex; gap: 8px; }
        #rail { display: flex; gap: 6px; overflow-x: auto; flex: 1; padding: 2px; }
        #rail img { height: 44px; border: 2px solid transparent; border-radius: 4px; cursor: pointer; opacity: 0.6; }
        #rail img.is-selected { border-color: var(--accent); opacity: 1; }
        @media (prefers-reduced-motion: reduce) { #stage img.is-fading, .gesture-ring { animation: none; } }
        #assertions, #trace, #diagnostics { margin-top: 16px; background: var(--panel); border: 1px solid var(--border); border-radius: 8px; padding: 14px 16px; }
        #assertions h2, #trace h2, #diagnostics h2 { font-size: 13px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--muted); margin: 0 0 10px; }
        #assertions ul { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 6px; }
        #assertions code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 13px; background: var(--paper); padding: 3px 8px; border-radius: 4px; display: inline-block; }
        #trace table { width: 100%; border-collapse: collapse; font-size: 13px; }
        #trace td { padding: 4px 0; border-bottom: 1px solid var(--border); }
        #trace td:last-child { text-align: right; color: var(--muted); white-space: nowrap; }
        #diagnostics ul { list-style: none; margin: 0 0 10px; padding: 0; display: flex; gap: 14px; flex-wrap: wrap; }
        #diagnostics a { color: var(--accent); text-decoration: none; }
        #diagnostics a:hover { text-decoration: underline; }
        #diagnostics pre { white-space: pre-wrap; font-size: 12px; color: var(--muted); margin: 0; }
        [hidden] { display: none !important; }
        </style>
        </head>
        <body>
        <header>
            <h1>Tetryon report</h1>
            <div id="summary" hidden></div>
        </header>
        <div id="layout">
            <nav id="sidebar" hidden>
                <input id="filter" type="text" placeholder="Filter tests…">
                <div class="sidebar-list"></div>
            </nav>
            <main>
                <h2 id="test-title"></h2>
                <div id="test-meta"></div>
                <div id="stage-wrap">
                    <div id="stage-header">
                        <span id="caption"></span>
                        <span id="step-meta"></span>
                    </div>
                    <div id="stage"><div id="stage-frame"><img id="stage-img" alt=""><div id="overlay"></div></div></div>
                    <div id="nav">
                        <div id="nav-controls">
                            <button id="play" type="button" aria-pressed="false">&#9654; Play</button>
                            <select id="speed" aria-label="Playback speed">
                                <option value="0.5">0.5&times;</option>
                                <option value="1" selected>1&times;</option>
                                <option value="2">2&times;</option>
                                <option value="4">4&times;</option>
                            </select>
                        </div>
                        <button id="prev" type="button">&larr; Prev</button>
                        <div id="rail"></div>
                        <button id="next" type="button">Next &rarr;</button>
                    </div>
                </div>
                <div id="assertions" hidden></div>
                <div id="trace" hidden></div>
                <div id="diagnostics" hidden></div>
            </main>
        </div>
        <script type="application/json" id="tetryon-report-data">__TETRYON_REPORT_DATA__</script>
        <script>
        (function () {
            'use strict';

            var data = JSON.parse(document.getElementById('tetryon-report-data').textContent);
            var state = { testIndex: 0, momentIndex: 0 };
            // Playback paces itself on the real gap between moments, clamped:
            // long enough to read a new caption, never so long that a slow
            // step turns into dead air.
            var MIN_FRAME_MS = 250;
            var MIN_CAPTION_MS = 900;
            var MIN_GESTURE_MS = 600;
            var MAX_HOLD_MS = 1500;
            var playTimer = null;
            var playSpeed = 1;

            var els = {
                summary: document.getElementById('summary'),
                sidebar: document.getElementById('sidebar'),
                sidebarList: document.querySelector('#sidebar .sidebar-list'),
                filter: document.getElementById('filter'),
                testTitle: document.getElementById('test-title'),
                testMeta: document.getElementById('test-meta'),
                caption: document.getElementById('caption'),
                stepMeta: document.getElementById('step-meta'),
                stageImg: document.getElementById('stage-img'),
                stageFrame: document.getElementById('stage-frame'),
                overlay: document.getElementById('overlay'),
                rail: document.getElementById('rail'),
                play: document.getElementById('play'),
                speed: document.getElementById('speed'),
                prev: document.getElementById('prev'),
                next: document.getElementById('next'),
                assertions: document.getElementById('assertions'),
                trace: document.getElementById('trace'),
                diagnostics: document.getElementById('diagnostics'),
            };

            function escapeHtml(text) {
                var div = document.createElement('div');
                div.textContent = text == null ? '' : String(text);
                return div.innerHTML;
            }

            function currentTest() {
                return data.tests[state.testIndex];
            }

            function initialTestIndex() {
                var firstFailed = data.tests.findIndex(function (t) { return !t.passed; });
                return firstFailed === -1 ? 0 : firstFailed;
            }

            function renderSummary() {
                if (data.tests.length <= 1) {
                    return;
                }
                var s = data.summary;
                els.summary.hidden = false;
                var bar = data.tests.map(function (t) {
                    return '<span class="summary-seg ' + (t.passed ? 'is-passed' : 'is-failed') + '"></span>';
                }).join('');
                els.summary.innerHTML = '<span class="summary-count">' + s.passed + '/' + s.total + ' passed</span>'
                    + '<div class="summary-bar">' + bar + '</div>';
            }

            function renderSidebar() {
                if (data.tests.length <= 1) {
                    return;
                }
                els.sidebar.hidden = false;
                var needle = els.filter.value.trim().toLowerCase();
                var html = '';
                data.tests.forEach(function (t, i) {
                    if (needle && t.title.toLowerCase().indexOf(needle) === -1) {
                        return;
                    }
                    var cls = 'sidebar-row ' + (t.passed ? 'is-passed' : 'is-failed') + (i === state.testIndex ? ' is-selected' : '');
                    html += '<button type="button" class="' + cls + '" data-test-index="' + i + '">'
                        + '<span class="badge">' + (t.passed ? '✓' : '✗') + '</span>'
                        + '<span class="row-title">' + escapeHtml(t.title) + '</span>'
                        + '<span class="row-steps">' + t.totalSteps + ' steps</span>'
                        + '</button>';
                });
                els.sidebarList.innerHTML = html;
            }

            function selectTest(index, keepPlaying) {
                if (!keepPlaying) {
                    stopPlaying();
                }
                state.testIndex = index;
                state.momentIndex = 0;
                renderSidebar();
                renderTest();
            }

            function renderTest() {
                var test = currentTest();
                els.testTitle.textContent = test.title;
                els.testMeta.textContent = (test.passed ? 'Passed' : 'Failed')
                    + (data.tests.length > 1 ? ' · test ' + test.index + ' of ' + test.total : '')
                    + ' · ' + test.totalSteps + ' steps';

                // One thumbnail per change of picture, not per moment.
                els.rail.innerHTML = test.moments.map(function (m, i) {
                    if (i > 0 && test.moments[i - 1].src === m.src) {
                        return '';
                    }
                    return '<img src="' + m.src + '" data-moment-index="' + i + '" alt="">';
                }).join('');

                renderMoment();
            }

            function renderMoment() {
                var test = currentTest();
                var moment = test.moments[state.momentIndex];

                if (els.stageImg.getAttribute('src') !== moment.src) {
                    // Crossfade: the outgoing frame stays behind as the
                    // background while the incoming one fades in over it.
                    var outgoing = els.stageImg.getAttribute('src');
                    els.stageFrame.style.backgroundImage = outgoing ? 'url("' + outgoing + '")' : '';
                    els.stageImg.src = moment.src;
                    els.stageImg.classList.remove('is-fading');
                    void els.stageImg.offsetWidth;
                    els.stageImg.classList.add('is-fading');
                }
                renderOverlay(moment.overlay);
                var upcoming = test.moments[state.momentIndex + 1];
                if (upcoming) {
                    new Image().src = upcoming.src;
                }
                els.caption.className = moment.outcome ? 'is-' + moment.outcome : '';
                els.caption.innerHTML = (moment.verified ? '<span class="verified-badge">✓</span>' : '') + escapeHtml(moment.caption);
                var skipped = realGapMs(test, state.momentIndex) - MAX_HOLD_MS;
                els.stepMeta.textContent = 'step ' + moment.stepIndex + '/' + moment.totalSteps
                    + (moment.durationMs ? ' · ' + moment.durationMs + 'ms' : '')
                    + (skipped > 0 ? ' · nothing changed for ' + (skipped / 1000).toFixed(1) + 's' : '');

                var current = null;
                Array.prototype.forEach.call(els.rail.querySelectorAll('img'), function (img) {
                    img.classList.remove('is-selected');
                    if (Number(img.dataset.momentIndex) <= state.momentIndex) {
                        current = img;
                    }
                });
                if (current) {
                    current.classList.add('is-selected');
                    els.rail.scrollLeft = current.offsetLeft - els.rail.offsetLeft - els.rail.clientWidth / 2;
                }

                els.prev.disabled = state.momentIndex === 0 && state.testIndex === 0;
                els.next.disabled = state.momentIndex === test.moments.length - 1 && state.testIndex === data.tests.length - 1;

                renderAssertions(moment);
                renderTrace(moment);
                renderDiagnostics(test);
            }

            function renderOverlay(o) {
                if (!o || !o.vw || !o.vh) {
                    els.overlay.innerHTML = '';
                    return;
                }
                var pct = function (value, of) { return (value / of * 100) + '%'; };
                var html = '<div class="gesture-ring" style="left:' + pct(o.x, o.vw) + ';top:' + pct(o.y, o.vh)
                    + ';width:' + pct(o.w, o.vw) + ';height:' + pct(o.h, o.vh) + '"></div>';
                if (o.options) {
                    html += '<ul class="gesture-options" style="left:' + pct(o.x, o.vw) + ';top:' + pct(o.y + o.h, o.vh)
                        + ';min-width:' + pct(o.w, o.vw) + '">'
                        + o.options.map(function (label) {
                            return '<li' + (label === o.selected ? ' class="is-selected"' : '') + '>' + escapeHtml(label) + '</li>';
                        }).join('') + '</ul>';
                }
                els.overlay.innerHTML = html;
            }

            function realGapMs(test, index) {
                var next = test.moments[index + 1];
                return next ? Math.max(0, (next.atMs || 0) - (test.moments[index].atMs || 0)) : 0;
            }

            function holdMs(test, index) {
                var moment = test.moments[index];
                var previous = test.moments[index - 1];
                var sameCaption = previous && previous.caption === moment.caption;
                var floor = !sameCaption ? MIN_CAPTION_MS : (moment.overlay ? MIN_GESTURE_MS : MIN_FRAME_MS);
                return Math.min(Math.max(realGapMs(test, index), floor), MAX_HOLD_MS) / playSpeed;
            }

            function renderAssertions(moment) {
                if (!moment.assertions || !moment.assertions.length) {
                    els.assertions.hidden = true;
                    return;
                }
                els.assertions.hidden = false;
                var items = moment.assertions.map(function (a) {
                    return '<li><code>' + escapeHtml(a) + '</code></li>';
                }).join('');
                els.assertions.innerHTML = '<h2>Assertions</h2><ul>' + items + '</ul>';
            }

            function renderTrace(moment) {
                if (!moment.selectorTrace) {
                    els.trace.hidden = true;
                    return;
                }
                els.trace.hidden = false;
                var rows = moment.selectorTrace.attempts.map(function (a) {
                    return '<tr><td>' + escapeHtml(a.description) + '</td><td>'
                        + (a.matchCount === 0 ? 'no matches' : a.matchCount + ' match' + (a.matchCount === 1 ? '' : 'es')) + '</td></tr>';
                }).join('');
                els.trace.innerHTML = '<h2>Selector attempts for "' + escapeHtml(moment.selectorTrace.target) + '"</h2><table>' + rows + '</table>';
            }

            function renderDiagnostics(test) {
                if (!test.diagnosticsDir) {
                    els.diagnostics.hidden = true;
                    return;
                }
                els.diagnostics.hidden = false;
                var dir = test.diagnosticsDir;
                var links = ['page.html', 'console.log', 'network.log', 'trace.log', 'browser-stderr.log'].map(function (file) {
                    return '<li><a href="' + dir + '/' + file + '" target="_blank" rel="noopener">' + file + '</a></li>';
                }).join('');
                els.diagnostics.innerHTML = '<h2>Diagnostics</h2><ul>' + links + '</ul>'
                    + '<pre>' + escapeHtml(test.diagnosticsReport || '') + '</pre>';
            }

            function advanceMoment() {
                var test = currentTest();
                if (state.momentIndex < test.moments.length - 1) {
                    state.momentIndex += 1;
                    renderMoment();
                    return true;
                }
                if (state.testIndex < data.tests.length - 1) {
                    selectTest(state.testIndex + 1, true);
                    return true;
                }
                return false;
            }

            function retreatMoment() {
                if (state.momentIndex > 0) {
                    state.momentIndex -= 1;
                    renderMoment();
                    return true;
                }
                if (state.testIndex > 0) {
                    var targetIndex = state.testIndex - 1;
                    var lastMomentIndex = data.tests[targetIndex].moments.length - 1;
                    selectTest(targetIndex, true);
                    state.momentIndex = lastMomentIndex;
                    renderMoment();
                    return true;
                }
                return false;
            }

            function stopPlaying() {
                if (playTimer) {
                    clearTimeout(playTimer);
                    playTimer = null;
                }
                els.play.innerHTML = '&#9654; Play';
                els.play.setAttribute('aria-pressed', 'false');
            }

            function playTick() {
                if (!advanceMoment()) {
                    stopPlaying();
                    return;
                }
                playTimer = setTimeout(playTick, holdMs(currentTest(), state.momentIndex));
            }

            function startPlaying() {
                els.play.innerHTML = '&#10074;&#10074; Pause';
                els.play.setAttribute('aria-pressed', 'true');
                playTimer = setTimeout(playTick, holdMs(currentTest(), state.momentIndex));
            }

            els.sidebarList.addEventListener('click', function (event) {
                var button = event.target.closest('[data-test-index]');
                if (button) {
                    selectTest(Number(button.dataset.testIndex));
                }
            });

            els.filter.addEventListener('input', renderSidebar);

            els.rail.addEventListener('click', function (event) {
                var img = event.target.closest('[data-moment-index]');
                if (img) {
                    stopPlaying();
                    state.momentIndex = Number(img.dataset.momentIndex);
                    renderMoment();
                }
            });

            els.prev.addEventListener('click', function () {
                stopPlaying();
                retreatMoment();
            });

            els.next.addEventListener('click', function () {
                stopPlaying();
                advanceMoment();
            });

            els.play.addEventListener('click', function () {
                if (playTimer) {
                    stopPlaying();
                } else {
                    startPlaying();
                }
            });

            els.speed.addEventListener('change', function () {
                playSpeed = Number(els.speed.value);
            });

            document.addEventListener('keydown', function (event) {
                if (event.target === els.filter) {
                    return;
                }
                if (event.key === 'ArrowLeft') {
                    els.prev.click();
                } else if (event.key === 'ArrowRight') {
                    els.next.click();
                } else if (event.key === ' ' || event.code === 'Space') {
                    event.preventDefault();
                    els.play.click();
                }
            });

            renderSummary();
            state.testIndex = initialTestIndex();
            renderSidebar();
            renderTest();
        })();
        </script>
        </body>
        </html>
        HTML_WRAP;
    }
}
