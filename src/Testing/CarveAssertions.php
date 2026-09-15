<?php

declare(strict_types=1);

namespace MarkupCarve\Tempest\Testing;

use MarkupCarve\Tempest\CarveRenderer;
use PHPUnit\Framework\Assert;

trait CarveAssertions
{
    public function assertCarveRenders(
        string $expected,
        string $source,
        ?CarveRenderer $renderer = null,
    ): void {
        Assert::assertStringContainsString(
            $expected,
            ($renderer ?? CarveRenderer::safe())->render($source),
        );
    }

    public function assertCarveIsSafe(
        string $source,
        string $unsafeHtml,
        ?CarveRenderer $renderer = null,
    ): void {
        Assert::assertStringNotContainsString(
            $unsafeHtml,
            ($renderer ?? CarveRenderer::safe())->render($source),
        );
    }

    public function assertCarveHasWarning(
        string $category,
        string $source,
        ?CarveRenderer $renderer = null,
    ): void {
        $warnings = ($renderer ?? CarveRenderer::safe())->renderWithReport($source)->warnings;
        Assert::assertContains($category, array_column($warnings, 'category'));
    }
}
