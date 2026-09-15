<?php

declare(strict_types=1);

namespace Tests;

use MarkupCarve\Tempest\CarveRenderer;
use PHPUnit\Framework\Attributes\Test;

final class CarveComponentTest extends IntegrationTestCase
{
    #[Test]
    public function testRendersCarveAndEscapesRawHtml(): void
    {
        $html = $this->view->render(
            __DIR__ . '/Fixtures/carve.view.php',
            document: <<<'CARVE'
                # Carve on Tempest

                /Rendered/ through an `x-carve` component.

                ```=html
                <script>alert('unsafe')</script>
                ```
                CARVE,
        );

        self::assertStringContainsString('<h1>Carve on Tempest</h1>', $html);
        self::assertStringContainsString('<em>Rendered</em> through an <code>x-carve</code> component.', $html);
        self::assertStringContainsString("&lt;script&gt;alert('unsafe')&lt;/script&gt;", $html);
        self::assertStringNotContainsString("<script>alert('unsafe')</script>", $html);
    }

    #[Test]
    public function testRendererIsASingleton(): void
    {
        self::assertSame(
            $this->container->get(CarveRenderer::class),
            $this->container->get(CarveRenderer::class),
        );
    }

    #[Test]
    public function testContentDefaultsToEmptyInput(): void
    {
        self::assertSame('', trim($this->view->render('<x-carve />')));
    }
}
