<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Core\Selector;

/**
 * Turns a human target ("Email", "Save changes", "@save-button") into the
 * ordered list of {@see Locator}s to try. Pure — no browser — so the priority
 * order is fully unit-testable. The order mirrors the spec: explicit selector,
 * then test attributes, label, accessible name, placeholder, button text, link
 * text, name, id, and finally any visible text.
 */
final class SelectorStrategy
{
    /**
     * @param  list<string>  $testAttributes
     * @return list<Locator>
     */
    public function candidates(string $target, array $testAttributes): array
    {
        $explicit = $this->explicit($target, $testAttributes);
        if ($explicit instanceof Locator) {
            return [$explicit];
        }

        $css = $this->cssString($target);
        $xpath = $this->xpathString($target);

        $candidates = [];
        foreach ($testAttributes as $attribute) {
            $candidates[] = Locator::css("[{$attribute}]", '['.$attribute.'='.$css.']');
        }

        $candidates[] = Locator::xpath('label', $this->labelExpression($xpath));
        $candidates[] = Locator::accessibleName($target);
        $candidates[] = Locator::css('placeholder', '[placeholder='.$css.']');
        $candidates[] = Locator::xpath('button text', $this->buttonExpression($xpath));
        $candidates[] = Locator::xpath('link text', './/a[normalize-space()='.$xpath.']');
        $candidates[] = Locator::css('name', '[name='.$css.']');

        if (preg_match('/^[A-Za-z][\w-]*$/', $target) === 1) {
            $candidates[] = Locator::css('id', '#'.$target);
        }

        $candidates[] = Locator::xpath('visible text', './/*[normalize-space(text())='.$xpath.']');

        return $candidates;
    }

    /**
     * The candidates for check()/uncheck(): the checkbox or radio a target
     * names, including one a text label is associated with by wrapping, `for=`,
     * or mere adjacency — the "styled label next to a hidden real input" pattern
     * (TodoMVC's `<input class="toggle"><label>Buy milk</label>`, custom toggles).
     *
     * Kept separate from {@see candidates()} on purpose: the adjacency
     * association must never hijack click()/doubleClick(), which want the label
     * itself, not the checkbox beside it (#138).
     *
     * @param  list<string>  $testAttributes
     * @return list<Locator>
     */
    public function checkableCandidates(string $target, array $testAttributes): array
    {
        $explicit = $this->explicit($target, $testAttributes);
        if ($explicit instanceof Locator) {
            return [$explicit];
        }

        $css = $this->cssString($target);
        $xpath = $this->xpathString($target);

        $candidates = [];
        foreach ($testAttributes as $attribute) {
            $candidates[] = Locator::css("[{$attribute}]", '['.$attribute.'='.$css.']');
        }

        $candidates[] = Locator::xpath('label', $this->checkboxForLabelExpression($xpath));
        $candidates[] = Locator::css('name', '[name='.$css.']');

        if (preg_match('/^[A-Za-z][\w-]*$/', $target) === 1) {
            $candidates[] = Locator::css('id', '#'.$target);
        }

        return $candidates;
    }

    /**
     * @param  list<string>  $testAttributes
     */
    private function explicit(string $target, array $testAttributes): ?Locator
    {
        if (str_starts_with($target, '@')) {
            $attribute = $testAttributes[0] ?? 'data-testid';

            return Locator::css('explicit test attribute', '['.$attribute.'='.$this->cssString(substr($target, 1)).']');
        }

        if (str_starts_with($target, '//') || str_starts_with($target, '(')) {
            return Locator::xpath('explicit xpath', $target);
        }

        if (str_starts_with($target, '#') || str_starts_with($target, '.') || str_starts_with($target, '[')) {
            return Locator::css('explicit css', $target);
        }

        return null;
    }

    /**
     * Relative (`.//`) so the expression scopes under a `within()` container and
     * still matches document-wide when unscoped. The inner `//label` is a lookup
     * for the label's `for` attribute, not the matched node.
     */
    private function labelExpression(string $xpath): string
    {
        return ".//label[normalize-space()={$xpath}]//input"
            ." | .//label[normalize-space()={$xpath}]//textarea"
            ." | .//label[normalize-space()={$xpath}]//select"
            ." | .//*[@id=//label[normalize-space()={$xpath}]/@for]";
    }

    /**
     * A checkbox/radio associated with a label of the given text — by wrapping,
     * by `for=`, or by being the immediately adjacent sibling (either order) of
     * a label that has neither. The adjacency branches are what make a hidden
     * real input behind a styled label drivable by that label's text (#138);
     * scoped to checkbox/radio and to the adjacent element so an unrelated input
     * can't be caught.
     */
    private function checkboxForLabelExpression(string $xpath): string
    {
        return ".//label[normalize-space()={$xpath}]//input[@type='checkbox' or @type='radio']"
            ." | .//input[(@type='checkbox' or @type='radio') and @id=//label[normalize-space()={$xpath}]/@for]"
            ." | .//input[(@type='checkbox' or @type='radio') and following-sibling::*[1][self::label][normalize-space()={$xpath}]]"
            ." | .//input[(@type='checkbox' or @type='radio') and preceding-sibling::*[1][self::label][normalize-space()={$xpath}]]";
    }

    private function buttonExpression(string $xpath): string
    {
        return ".//button[normalize-space()={$xpath}]"
            ." | .//input[(@type='submit' or @type='button' or @type='reset') and @value={$xpath}]";
    }

    private function cssString(string $value): string
    {
        return '"'.addcslashes($value, '"\\').'"';
    }

    /**
     * Build an XPath string literal that survives embedded quotes.
     */
    private function xpathString(string $value): string
    {
        if (! str_contains($value, '"')) {
            return '"'.$value.'"';
        }

        if (! str_contains($value, "'")) {
            return "'".$value."'";
        }

        return 'concat("'.str_replace('"', '",\'"\',"', $value).'")';
    }
}
