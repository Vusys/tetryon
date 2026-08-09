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

        $candidates[] = Locator::xpath('label', $this->labelExpression($xpath), $this->labelPierce($target));
        $candidates[] = Locator::accessibleName($target);
        $candidates[] = Locator::css('placeholder', '[placeholder='.$css.']');
        $candidates[] = Locator::xpath('button text', $this->buttonExpression($xpath), $this->buttonPierce($target));
        $candidates[] = Locator::xpath('link text', './/a[normalize-space()='.$xpath.']', $this->linkPierce($target));
        $candidates[] = Locator::css('name', '[name='.$css.']');

        if (preg_match('/^[A-Za-z][\w-]*$/', $target) === 1) {
            $candidates[] = Locator::css('id', '#'.$target);
        }

        $candidates[] = Locator::xpath('visible text', './/*[normalize-space(text())='.$xpath.']', $this->visibleTextPierce($target));

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

        $candidates[] = Locator::xpath('label', $this->checkboxForLabelExpression($xpath), $this->checkboxForLabelPierce($target));
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

    // ── Shadow-piercing matchers ────────────────────────────────────────────
    //
    // XPath can't cross shadow boundaries, so each text/label strategy carries a
    // JS equivalent — a `(root) => Element[]` the driver runs inside every open
    // shadow root when the native XPath finds nothing (#162). Each mirrors its
    // XPath twin's semantics (normalize-space text comparison).

    private function labelPierce(string $target): string
    {
        $t = $this->jsString($target);

        return "(root)=>{const ns=s=>(s||'').replace(/\\s+/g,' ').trim();const out=[];"
            ."root.querySelectorAll('label').forEach(l=>{if(ns(l.textContent)!=={$t})return;"
            ."l.querySelectorAll('input,textarea,select').forEach(c=>out.push(c));"
            ."const f=l.getAttribute('for');if(f){const t=root.querySelector('#'+CSS.escape(f));if(t)out.push(t);}});"
            .'return out;}';
    }

    private function checkboxForLabelPierce(string $target): string
    {
        $t = $this->jsString($target);

        return "(root)=>{const ns=s=>(s||'').replace(/\\s+/g,' ').trim();"
            ."const cr=el=>el&&el.tagName==='INPUT'&&(el.type==='checkbox'||el.type==='radio');const out=[];"
            ."root.querySelectorAll('label').forEach(l=>{if(ns(l.textContent)!=={$t})return;"
            ."l.querySelectorAll('input').forEach(c=>{if(cr(c))out.push(c);});"
            ."const f=l.getAttribute('for');if(f){const t=root.querySelector('#'+CSS.escape(f));if(cr(t))out.push(t);}"
            .'const p=l.previousElementSibling,n=l.nextElementSibling;if(cr(p))out.push(p);if(cr(n))out.push(n);});'
            .'return out;}';
    }

    private function buttonPierce(string $target): string
    {
        $t = $this->jsString($target);

        return "(root)=>{const ns=s=>(s||'').replace(/\\s+/g,' ').trim();const out=[];"
            ."root.querySelectorAll('button').forEach(b=>{if(ns(b.textContent)==={$t})out.push(b);});"
            ."root.querySelectorAll('input[type=submit],input[type=button],input[type=reset]').forEach(i=>{if(i.value==={$t})out.push(i);});"
            .'return out;}';
    }

    private function linkPierce(string $target): string
    {
        $t = $this->jsString($target);

        return "(root)=>{const ns=s=>(s||'').replace(/\\s+/g,' ').trim();"
            ."return Array.from(root.querySelectorAll('a')).filter(a=>ns(a.textContent)==={$t});}";
    }

    private function visibleTextPierce(string $target): string
    {
        $t = $this->jsString($target);

        return "(root)=>{const ns=s=>(s||'').replace(/\\s+/g,' ').trim();const out=[];"
            ."root.querySelectorAll('*').forEach(el=>{"
            ."const t=ns(Array.from(el.childNodes).filter(n=>n.nodeType===3).map(n=>n.textContent).join(''));"
            ."if(t==={$t})out.push(el);});"
            .'return out;}';
    }

    private function jsString(string $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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
