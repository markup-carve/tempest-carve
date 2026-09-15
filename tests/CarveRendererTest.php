<?php

declare(strict_types=1);

namespace Tests;

use MarkupCarve\Tempest\CarveRenderer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CarveRendererTest extends TestCase
{
    #[Test]
    public function testRendersCoreCarve(): void
    {
        $html = CarveRenderer::safe()->render("# Heading\n\n/Emphasis/");

        self::assertSame(
            "<section id=\"Heading\">\n  <h1>Heading</h1>\n  <p><em>Emphasis</em></p>\n</section>\n",
            $html,
        );
    }

    #[Test]
    public function testSafeModeEscapesRawHtml(): void
    {
        $source = "```=html\n<script>alert('unsafe')</script>\n```";

        $html = CarveRenderer::safe()->render($source);

        self::assertStringContainsString("&lt;script&gt;alert('unsafe')&lt;/script&gt;", $html);
        self::assertStringNotContainsString("<script>alert('unsafe')</script>", $html);
    }

    #[Test]
    public function testRendersEmptyInput(): void
    {
        self::assertSame('', CarveRenderer::safe()->render(''));
    }
}
