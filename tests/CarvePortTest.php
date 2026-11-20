<?php

declare(strict_types=1);

namespace Tests;

use MarkupCarve\Carve\Include\IncludeResolver;
use MarkupCarve\Carve\Node;
use MarkupCarve\Carve\Output\OutputWriter;
use MarkupCarve\Tempest\CarveConfig;
use MarkupCarve\Tempest\CarveRenderer;
use MarkupCarve\Tempest\ExtensionFactory;
use MarkupCarve\Tempest\IncludeOptions;
use MarkupCarve\Tempest\Validation\ValidCarve;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CarvePortTest extends TestCase
{
    #[Test]
    public function testRendersCarveWithSecureDefaultsAndOptions(): void
    {
        $renderer = CarveRenderer::safe(new CarveConfig(headingIds: 'lower', smartTypography: false));
        self::assertSame(
            "<section id=\"hello\">\n  <h1>Hello</h1>\n  <p>One -- two</p>\n</section>",
            $renderer->render("= Hello\n\nOne -- two\n"),
        );
        self::assertStringNotContainsString('<script>', $renderer->render("```=html\n<script>x</script>\n```\n"));
        self::assertStringContainsString('href=""', $renderer->render('[x](javascript:alert(1))'));
    }

    #[Test]
    public function testConfiguredTextWriterStillOverridesPlainOutput(): void
    {
        $writer = new class implements OutputWriter {
            public function target(): string
            {
                return 'text';
            }

            public function write(Node $tree, array $options = []): string
            {
                return (string)count($tree->children ?? []);
            }
        };
        $renderer = CarveRenderer::safe(new CarveConfig(writers: [$writer]));
        self::assertSame('2', $renderer->renderPlainText("a\n\nb\n"));
    }

    #[Test]
    public function testStandardOutputWritersAreAvailable(): void
    {
        $renderer = CarveRenderer::safe();
        self::assertSame("a\n\nb\n", $renderer->renderPlainText("a\n\nb\n"));
        self::assertSame("a\n", $renderer->renderMarkdown("a\n"));
        self::assertStringContainsString('a', $renderer->renderAnsi("a\n"));
    }

    #[Test]
    public function testRendersIncludesThroughTheCarveResolver(): void
    {
        $resolver = new class implements IncludeResolver {
            public function resolve(array $request): array
            {
                return $request['path'] === 'chapter.crv'
                    ? ['text' => "= Included\n", 'id' => 'db:chapter']
                    : ['error' => 'not_found'];
            }
        };
        $result = CarveRenderer::safe(includeResolver: $resolver)->renderIncluded('{{ chapter.crv }}');
        self::assertStringContainsString('Included', $result->html);
        self::assertSame([['target' => 'db:chapter', 'resolved' => true]], $result->dependencies);
    }

    #[Test]
    public function testIncludedRenderingReturnsRenderLossDiagnostics(): void
    {
        $resolver = new class implements IncludeResolver {
            public function resolve(array $request): array
            {
                return $request['path'] === 'chapter'
                    ? ['text' => '[x](javascript:alert(1))', 'id' => 'db:chapter']
                    : ['error' => 'not_found'];
            }
        };
        $result = CarveRenderer::safe(includeResolver: $resolver)->renderIncluded('{{ chapter }}');
        self::assertSame('destination-denied', $result->warnings[0]['code']);
        self::assertSame('render-loss', $result->warnings[0]['channel']);
    }

    #[Test]
    public function testLintFormatAndHtmlImportUseCarve(): void
    {
        $renderer = CarveRenderer::safe();
        self::assertSame('markdown-strong-double-star', $renderer->lint('**bold**')[0]->rule);
        self::assertSame("= Title\n\nText\n", $renderer->format("# Title\n\nText  \n"));
        self::assertSame("*Bold*\n", $renderer->fromHtml('<strong>Bold</strong>'));
    }

    #[Test]
    public function testValidationUsesCarveProfilesAndLint(): void
    {
        self::assertTrue((new ValidCarve())->isValid('/fine/'));
        self::assertFalse((new ValidCarve(lint: true))->isValid('**bold**'));
    }

    #[Test]
    public function testExtensionFactoryRegistersCarveFences(): void
    {
        $extension = (new ExtensionFactory())->create('mermaid');
        $html = CarveRenderer::safe(extensions: [$extension])->render("```mermaid\ngraph TD\n```\n");
        self::assertStringContainsString('class="mermaid"', $html);
    }

    #[Test]
    public function testInlineWikiExtensionRendersLinks(): void
    {
        $extension = (new ExtensionFactory())->create(['type' => 'wikilinks', 'url_template' => '/w/{page}']);
        self::assertStringContainsString('href="/w/tiger"', CarveRenderer::safe(extensions: [$extension])->render(':wiki[Tiger]'));
    }

    #[Test]
    public function testIncludeCallLimitIsAvailable(): void
    {
        $resolver = new class implements IncludeResolver {
            public function resolve(array $request): array
            {
                return $request['path'] === 'resolved'
                    ? ['text' => 'ok', 'id' => 'resolved']
                    : ['error' => 'not_found'];
            }
        };
        $renderer = CarveRenderer::safe(includeResolver: $resolver);
        $result = $renderer->renderIncluded("{{ a }}\n\n{{ b }}", new IncludeOptions(warningLimit: 1));
        self::assertCount(1, array_filter($result->warnings, static fn (array $warning): bool => $warning['code'] === 'include-not-found'));
        self::assertGreaterThanOrEqual(1, $result->suppressedWarnings);
    }
}
