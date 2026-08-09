<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\TodoMvc;

use Override;
use PHPUnit\Framework\Attributes\Group;

/**
 * Backbone — the oldest idiom in the set: per-model views, Backbone.sync,
 * underscore templates, and unbundled <script> tags loaded individually rather
 * than as one bundle. If any auto-wait assumption quietly depends on a modern
 * bundler's load ordering, this is where it shows. Like jQuery, it removes
 * `.clear-completed` from the DOM when nothing is completed.
 */
#[Group('todomvc')]
final class BackboneAppTest extends TodoMvcTestCase
{
    #[Override]
    protected function app(): TodoMvcApp
    {
        return new TodoMvcApp(
            name: 'backbone',
            path: 'examples/backbone/dist/',
        );
    }
}
