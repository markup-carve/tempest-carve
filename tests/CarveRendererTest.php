<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use MarkupCarve\Carve\Extension\HeadingNumbersExtension;
use MarkupCarve\Carve\Renderer\SmartTypographyMode;
use MarkupCarve\Carve\Renderer\SoftBreakMode;
use MarkupCarve\Tempest\CarveConfig;
use MarkupCarve\Tempest\CarveProfile;
use MarkupCarve\Tempest\CarveRenderer;
use MarkupCarve\Tempest\IncludeOptions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Tempest\Cache\GenericCache;
use Tests\Fixtures\DatabaseIncludeResolver;
use Tests\Fixtures\NonSerializableExtension;
use UnexpectedValueException;

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

    #[Test]
    public function testRendersAdditionalOutputFormats(): void
    {
        $renderer = CarveRenderer::safe();
        $source = "# Heading\n\n/Emphasis/";

        self::assertSame("# Heading\n\n*Emphasis*\n", $renderer->renderMarkdown($source));
        self::assertSame("Heading\n\nEmphasis\n", $renderer->renderPlainText($source));
        self::assertStringContainsString("\033[3mEmphasis\033[0m", $renderer->renderAnsi($source));
    }

    #[Test]
    public function testAlternativeOutputsTreatRawHtmlAsText(): void
    {
        $renderer = CarveRenderer::safe();
        $source = "```=html\n<script>alert(1)</script>\n```";

        self::assertSame("&lt;script&gt;alert(1)&lt;/script&gt;\n", $renderer->renderMarkdown($source));
        self::assertSame("\n", $renderer->renderPlainText($source));
        self::assertStringContainsString('[raw:html] <script>alert(1)</script>', $renderer->renderAnsi($source));
    }

    #[Test]
    public function testExtensionsRemainIsolatedBetweenOutputConverters(): void
    {
        $renderer = CarveRenderer::safe(extensions: [new HeadingNumbersExtension()]);

        $firstHtml = $renderer->renderHtml('# Heading');
        $renderer->renderMarkdown('# Heading');
        $secondHtml = $renderer->renderHtml('# Heading');

        self::assertStringContainsString('<span class="section-number">1</span>', $firstHtml);
        self::assertSame($firstHtml, $secondHtml);
    }

    #[Test]
    public function testAppliesRenderingConfiguration(): void
    {
        $renderer = CarveRenderer::safe(new CarveConfig(
            softBreakMode: SoftBreakMode::Space,
            smartTypography: SmartTypographyMode::Source,
            sourceLines: true,
        ));

        $html = $renderer->render("# Heading\n\nOne\ntwo -- ...");

        self::assertStringContainsString('<h1 data-source-line="1">Heading</h1>', $html);
        self::assertStringContainsString('<p data-source-line="3">One two -- ...</p>', $html);
        self::assertSame("One two\n", $renderer->renderMarkdown("One\ntwo"));
        self::assertSame("One two\n", $renderer->renderPlainText("One\ntwo"));
        self::assertSame("One two\n", $renderer->renderAnsi("One\ntwo"));
    }

    #[Test]
    public function testReturnsDiagnosticsAndProfileViolations(): void
    {
        $warnings = CarveRenderer::safe()->renderWithReport('[text][missing]');

        self::assertSame('reference', $warnings->warnings[0]['category']);
        self::assertSame(1, $warnings->warnings[0]['line']);

        $losses = CarveRenderer::safe()->renderWithReport("```=latex\n\\textbf{x}\n```");

        self::assertSame('raw-format-dropped', $losses->losses[0]['code']);
        self::assertSame(1, $losses->totalLosses);

        $violations = CarveRenderer::safe(
            new CarveConfig(profile: CarveProfile::Comment),
        )->renderWithReport('# Heading');

        self::assertSame('heading', $violations->profileViolations[0]['nodeType']);
    }

    #[Test]
    public function testCachesEachOutputIndependently(): void
    {
        $adapter = new ArrayAdapter();
        $renderer = CarveRenderer::safe(
            new CarveConfig(cacheEnabled: true),
            new GenericCache($adapter),
        );

        $renderer->renderHtml('/cached/');
        $renderer->renderHtml('/cached/');
        $renderer->renderPlainText('/cached/');

        self::assertCount(2, $adapter->getValues());
    }

    #[Test]
    public function testCacheSeparatesRenderingConfiguration(): void
    {
        $adapter = new ArrayAdapter();
        $cache = new GenericCache($adapter);

        CarveRenderer::safe(new CarveConfig(cacheEnabled: true), $cache)->render('# Heading');
        CarveRenderer::safe(new CarveConfig(cacheEnabled: true, sourceLines: true), $cache)->render('# Heading');
        CarveRenderer::safe(new CarveConfig(
            smartTypography: SmartTypographyMode::Source,
            cacheEnabled: true,
        ), $cache)->render('# Heading');
        CarveRenderer::safe(new CarveConfig(
            profile: CarveProfile::Article,
            cacheEnabled: true,
        ), $cache)->render('# Heading');
        CarveRenderer::safe(
            new CarveConfig(cacheEnabled: true),
            $cache,
            [new HeadingNumbersExtension(minLevel: 2)],
        )->render('# Heading');

        self::assertCount(5, $adapter->getValues());
    }

    #[Test]
    public function testRepairsANonStringCachedValue(): void
    {
        $adapter = new ArrayAdapter();
        $renderer = CarveRenderer::safe(
            new CarveConfig(cacheEnabled: true),
            new GenericCache($adapter),
        );

        $expected = $renderer->render('/cached/');
        $key = array_key_first($adapter->getValues());
        self::assertIsString($key);
        $adapter->save($adapter->getItem($key)->set(false));

        self::assertSame($expected, $renderer->render('/cached/'));
        self::assertIsString($adapter->getItem($key)->get());
    }

    #[Test]
    public function testCachingNonSerializableExtensionsRequiresASalt(): void
    {
        $this->expectException(UnexpectedValueException::class);

        CarveRenderer::safe(
            new CarveConfig(cacheEnabled: true),
            new GenericCache(new ArrayAdapter()),
            [new NonSerializableExtension()],
        );
    }

    #[Test]
    public function testCachingNonSerializableExtensionsAcceptsAnExplicitSalt(): void
    {
        $renderer = CarveRenderer::safe(
            new CarveConfig(cacheEnabled: true, cacheKeySalt: 'custom-v1'),
            new GenericCache(new ArrayAdapter()),
            [new NonSerializableExtension()],
        );

        self::assertStringContainsString('<em>cached</em>', $renderer->render('/cached/'));
    }

    #[Test]
    public function testOverridesProfilePerRender(): void
    {
        $renderer = CarveRenderer::safe(new CarveConfig(profile: CarveProfile::Article));

        self::assertStringContainsString('<h1>Heading</h1>', $renderer->render('# Heading'));
        self::assertStringContainsString('<p># Heading</p>', $renderer->render('# Heading', CarveProfile::Comment));
    }

    #[Test]
    public function testSelectsNamedRenderers(): void
    {
        $comment = CarveRenderer::safe(new CarveConfig(profile: CarveProfile::Comment));
        $renderer = CarveRenderer::safe(namedRenderers: ['comment' => $comment]);

        self::assertStringContainsString('<p># Heading</p>', $renderer->named('comment')->render('# Heading'));
    }

    #[Test]
    public function testRendersIncludesWithDependenciesAndWarnings(): void
    {
        $resolver = new DatabaseIncludeResolver();
        $renderer = CarveRenderer::safe(includeResolver: $resolver);

        $result = $renderer->renderIncluded("{{ chapter }}\n\n{{ missing }}");

        self::assertStringContainsString('<h1>Database chapter</h1>', $result->html);
        self::assertSame([
            ['target' => 'db:chapter', 'resolved' => true],
            ['target' => 'missing', 'resolved' => false],
        ], $result->dependencies);
        self::assertSame('include', $result->warnings[0]['category']);
        self::assertStringContainsString('missing', $result->warnings[0]['message']);
    }

    #[Test]
    public function testIncludeCacheChangesWithResolverVersion(): void
    {
        $adapter = new ArrayAdapter();
        $resolver = new DatabaseIncludeResolver();
        $renderer = CarveRenderer::safe(
            new CarveConfig(cacheEnabled: true),
            new GenericCache($adapter),
            includeResolver: $resolver,
        );

        $first = $renderer->renderIncluded('{{ chapter }}');
        $resolver->documents['chapter'] = '# Changed chapter';
        $resolver->version = '2';
        $second = $renderer->renderIncluded('{{ chapter }}');

        self::assertStringContainsString('Database chapter', $first->html);
        self::assertStringContainsString('Changed chapter', $second->html);
        self::assertCount(2, $adapter->getValues());
    }

    #[Test]
    public function testIncludeCacheSeparatesOptions(): void
    {
        $adapter = new ArrayAdapter();
        $renderer = CarveRenderer::safe(
            new CarveConfig(cacheEnabled: true),
            new GenericCache($adapter),
            includeResolver: new DatabaseIncludeResolver(),
        );

        $renderer->renderIncluded('{{ chapter }}', new IncludeOptions(currentPath: 'one'));
        $renderer->renderIncluded('{{ chapter }}', new IncludeOptions(currentPath: 'two'));

        self::assertCount(2, $adapter->getValues());
    }

    #[Test]
    public function testSkipsIncludeCacheWhenResolverChangesDuringExpansion(): void
    {
        $adapter = new ArrayAdapter();
        $resolver = new DatabaseIncludeResolver(changeVersionOnResolve: true);
        $renderer = CarveRenderer::safe(
            new CarveConfig(cacheEnabled: true),
            new GenericCache($adapter),
            includeResolver: $resolver,
        );

        $result = $renderer->renderIncluded('{{ chapter }}');

        self::assertStringContainsString('Database chapter', $result->html);
        self::assertCount(0, $adapter->getValues());
    }

    #[Test]
    public function testProfileOverrideAppliesToIncludedContent(): void
    {
        $renderer = CarveRenderer::safe(includeResolver: new DatabaseIncludeResolver());

        $result = $renderer->renderIncluded('{{ chapter }}', profile: CarveProfile::Comment);

        self::assertStringContainsString('<p># Database chapter</p>', $result->html);
    }

    #[Test]
    public function testRejectsUnknownNamedRenderer(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CarveRenderer::safe()->named('missing');
    }

    #[Test]
    public function testRequiresAnIncludeResolver(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CarveRenderer::safe()->renderIncluded('{{ chapter }}');
    }
}
