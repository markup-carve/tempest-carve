<?php

declare(strict_types=1);

namespace Tests;

use MarkupCarve\Carve\Extension\HeadingNumbersExtension;
use MarkupCarve\Tempest\CarveConfig;
use MarkupCarve\Tempest\CarveProfile;
use MarkupCarve\Tempest\CarveRenderer;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\DatabaseIncludeResolver;

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

    #[Test]
    public function testDiscoversDefaultCarveConfiguration(): void
    {
        self::assertInstanceOf(CarveConfig::class, $this->container->get(CarveConfig::class));
    }

    #[Test]
    public function testInitializesConfiguredExtensions(): void
    {
        $this->container->config(new CarveConfig(
            extensions: [HeadingNumbersExtension::class],
        ));

        $html = $this->container->get(CarveRenderer::class)->render('# Heading');

        self::assertStringContainsString('<span class="section-number">1</span> Heading', $html);
    }

    #[Test]
    public function testInitializesConfiguredCache(): void
    {
        $cache = $this->cache->fake();
        $this->container->config(new CarveConfig(cacheEnabled: true));

        $this->container->get(CarveRenderer::class)->render('/cached/');

        $cache->assertNotEmpty();
    }

    #[Test]
    public function testInitializesNamedRenderersAndIncludeResolver(): void
    {
        $this->container->config(new CarveConfig(
            namedRenderers: [
                'comments' => new CarveConfig(profile: CarveProfile::Comment),
            ],
            includeResolver: DatabaseIncludeResolver::class,
        ));

        $renderer = $this->container->get(CarveRenderer::class);

        self::assertStringContainsString('<p># Heading</p>', $renderer->named('comments')->render('# Heading'));
        self::assertStringContainsString('Database chapter', $renderer->renderIncluded('{{ chapter }}')->html);
    }
}
