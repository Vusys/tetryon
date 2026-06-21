<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\Core\Selector;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\Core\Selector\Locator;
use Vusys\Tetryon\Core\Selector\SelectorStrategy;

#[CoversClass(SelectorStrategy::class)]
#[CoversClass(Locator::class)]
final class SelectorStrategyTest extends TestCase
{
    private const array TEST_ATTRIBUTES = ['data-testid', 'data-test', 'data-cy'];

    public function test_a_plain_target_produces_the_priority_order(): void
    {
        self::assertSame(
            [
                '[data-testid]', '[data-test]', '[data-cy]',
                'label', 'accessible name', 'placeholder',
                'button text', 'link text', 'name', 'id', 'visible text',
            ],
            $this->descriptions('Email'),
        );
    }

    public function test_test_attribute_locator_is_css_with_a_quoted_value(): void
    {
        $candidates = new SelectorStrategy()->candidates('Email', ['data-testid']);

        self::assertSame(['type' => 'css', 'value' => '[data-testid="Email"]'], $candidates[0]->bidi);
    }

    public function test_accessible_name_locator_carries_the_target(): void
    {
        $accessible = $this->byDescription('Email', 'accessible name');

        self::assertSame(['type' => 'accessibility', 'value' => ['name' => 'Email']], $accessible->bidi);
    }

    public function test_at_marker_is_a_single_explicit_test_attribute(): void
    {
        $candidates = new SelectorStrategy()->candidates('@save-button', self::TEST_ATTRIBUTES);

        self::assertCount(1, $candidates);
        self::assertSame(['type' => 'css', 'value' => '[data-testid="save-button"]'], $candidates[0]->bidi);
    }

    public function test_hash_and_bracket_markers_are_explicit_css(): void
    {
        foreach (['#save', '[data-role="x"]', '.btn'] as $selector) {
            $candidates = new SelectorStrategy()->candidates($selector, self::TEST_ATTRIBUTES);
            self::assertCount(1, $candidates);
            self::assertSame(['type' => 'css', 'value' => $selector], $candidates[0]->bidi);
        }
    }

    public function test_double_slash_marker_is_explicit_xpath(): void
    {
        $candidates = new SelectorStrategy()->candidates('//button[@id="x"]', self::TEST_ATTRIBUTES);

        self::assertCount(1, $candidates);
        self::assertSame('xpath', $candidates[0]->bidi['type']);
    }

    public function test_id_candidate_is_skipped_for_non_identifier_targets(): void
    {
        self::assertNotContains('id', $this->descriptions('Save changes'));
        self::assertContains('id', $this->descriptions('Email'));
    }

    public function test_xpath_literals_survive_embedded_double_quotes(): void
    {
        $link = $this->byDescription('Say "hi"', 'link text');

        // With a double quote in the target, the XPath literal switches to single quotes.
        self::assertSame('//a[normalize-space()=\'Say "hi"\']', $link->bidi['value']);
    }

    /**
     * @return list<string>
     */
    private function descriptions(string $target): array
    {
        return array_map(
            static fn (Locator $locator): string => $locator->description,
            new SelectorStrategy()->candidates($target, self::TEST_ATTRIBUTES),
        );
    }

    private function byDescription(string $target, string $description): Locator
    {
        foreach (new SelectorStrategy()->candidates($target, self::TEST_ATTRIBUTES) as $locator) {
            if ($locator->description === $description) {
                return $locator;
            }
        }

        self::fail("No candidate with description \"{$description}\".");
    }
}
