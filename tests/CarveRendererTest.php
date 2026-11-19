<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use MarkupCarve\Carve\Extension\ExtensionDefinition;
use MarkupCarve\Carve\RenderError;
use MarkupCarve\Tempest\CarveConfig;
use MarkupCarve\Tempest\CarveProfile;
use MarkupCarve\Tempest\CarveRenderer;
use MarkupCarve\Tempest\IncludeOptions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Tempest\Cache\GenericCache;
use Tests\Fixtures\DatabaseIncludeResolver;
use Tests\Fixtures\NonSerializableCarveExtension;
use UnexpectedValueException;

final class CarveRendererTest extends TestCase
{
    #[Test]
    public function testRendersCoreCarve(): void
    {
        $html = CarveRenderer::safe()->render("# Heading\n\n/Emphasis/");

        self::assertSame(
            "<section id=\"Heading\">\n  <h1>Heading</h1>\n  <p><em>Emphasis</em></p>\n</section>",
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
        if (getenv('CARVE2_ENABLE_PENDING_TESTS') !== '1') {
            $this->markTestSkipped('Carve output writers pending in carve-php issue 5235.');
        }
        $renderer = CarveRenderer::safe();
        $source = "# Heading\n\n/Emphasis/";

        self::assertSame("# Heading\n\n*Emphasis*\n", $renderer->renderMarkdown($source));
        self::assertSame("Heading\n\nEmphasis\n", $renderer->renderPlainText($source));
        self::assertStringContainsString("\033[3mEmphasis\033[0m", $renderer->renderAnsi($source));
    }

    #[Test]
    public function testAlternativeOutputsTreatRawHtmlAsText(): void
    {
        if (getenv('CARVE2_ENABLE_PENDING_TESTS') !== '1') {
            $this->markTestSkipped('Carve output writers pending in carve-php issue 5235.');
        }
        $renderer = CarveRenderer::safe();
        $source = "```=html\n<script>alert(1)</script>\n```";

        self::assertSame("&lt;script&gt;alert(1)&lt;/script&gt;\n", $renderer->renderMarkdown($source));
        self::assertSame("\n", $renderer->renderPlainText($source));
        self::assertStringContainsString('[raw:html] <script>alert(1)</script>', $renderer->renderAnsi($source));
    }

    #[Test]
    public function testExtensionsRemainIsolatedBetweenOutputConverters(): void
    {
        if (getenv('CARVE2_ENABLE_PENDING_TESTS') !== '1') {
            $this->markTestSkipped('Carve heading number extension and output writers pending in carve-php issues 5238 and 5235.');
        }
        // Placeholder definition until the C2-X document transform hook in carve-php issue 5238.
        $renderer = CarveRenderer::safe(extensions: [new ExtensionDefinition('tempest.heading-number', '1')]);

        $firstHtml = $renderer->renderHtml('# Heading');
        $renderer->renderMarkdown('# Heading');
        $secondHtml = $renderer->renderHtml('# Heading');

        self::assertStringContainsString('<span class="section-number">1</span>', $firstHtml);
        self::assertSame($firstHtml, $secondHtml);
    }

    #[Test]
    public function testAppliesRenderingConfiguration(): void
    {
        // C2-H-131 and C2-H-121 replace the old renderer enums with option values.
        $renderer = CarveRenderer::safe(new CarveConfig(
            smartTypography: false,
            sourceLines: true,
        ));

        $html = $renderer->render("# Heading\n\nOne\ntwo -- ...");

        self::assertStringContainsString('<h1 data-source-line="1">Heading</h1>', $html);
        self::assertStringContainsString("<p data-source-line=\"3\">One\ntwo -- ...</p>", $html);
    }

    #[Test]
    public function testReturnsDiagnosticsAndProfileViolations(): void
    {
        // C2-N-083 reports unresolved includes in the parse channel.
        $warnings = CarveRenderer::safe()->renderWithReport('{{ missing }}');

        self::assertSame('include', $warnings->warnings[0]['category']);
        self::assertSame(1, $warnings->warnings[0]['line']);

        $losses = CarveRenderer::safe()->renderWithReport("```=latex\n\\textbf{x}\n```");

        self::assertSame('raw-format-dropped', $losses->losses[0]['code']);
        self::assertSame(1, $losses->totalLosses);

        $violations = CarveRenderer::safe(
            new CarveConfig(profile: CarveProfile::Comment),
        )->renderWithReport('# Heading');

        self::assertSame('heading', $violations->profileViolations[0]['nodeType']);
    }

    /**
     * carve-php 0.1.11 added `destination-denied` to the render-loss codes. On
     * 0.1.9 the same document reported nothing, so strict mode let a denied
     * destination through silently.
     */
    #[Test]
    public function testReportsADeniedDestinationAsARenderLoss(): void
    {
        $report = CarveRenderer::safe()->renderWithReport('[x](javascript:alert(1))');

        self::assertSame('destination-denied', $report->losses[0]['code']);
        self::assertSame('inline', $report->losses[0]['nodeType']);
        self::assertSame(1, $report->totalLosses);

        $image = CarveRenderer::safe()->renderWithReport('![a](javascript:alert(1))');

        self::assertSame('destination-denied', $image->losses[0]['code']);
        self::assertSame(1, $image->totalLosses);
    }

    #[Test]
    public function testStrictLossesRejectsADeniedDestination(): void
    {
        // C2-T-040 names Carve's strict render error.
        $this->expectException(RenderError::class);

        CarveRenderer::safe()->renderWithReport('[x](javascript:alert(1))', strictLosses: true);
    }

    #[Test]
    public function testCachesEachOutputIndependently(): void
    {
        if (getenv('CARVE2_ENABLE_PENDING_TESTS') !== '1') {
            $this->markTestSkipped('Carve text writer pending in carve-php issue 5235.');
        }
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
            smartTypography: false,
            cacheEnabled: true,
        ), $cache)->render('# Heading');
        CarveRenderer::safe(new CarveConfig(
            profile: CarveProfile::Article,
            cacheEnabled: true,
        ), $cache)->render('# Heading');
        // C2-H-131 replaces heading id extensions with the headingIds option.
        CarveRenderer::safe(new CarveConfig(headingIds: 'lower', cacheEnabled: true), $cache)->render('# Heading');

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
            [new NonSerializableCarveExtension()],
        );
    }

    #[Test]
    public function testCachingNonSerializableExtensionsAcceptsAnExplicitSalt(): void
    {
        if (getenv('CARVE2_ENABLE_PENDING_TESTS') !== '1') {
            $this->markTestSkipped('Carve document transform extensions pending in carve-php issue 5238.');
        }
        $renderer = CarveRenderer::safe(
            new CarveConfig(cacheEnabled: true, cacheKeySalt: 'custom-v1'),
            new GenericCache(new ArrayAdapter()),
            [new NonSerializableCarveExtension()],
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
        self::assertIsString($result->warnings[0]['message']);
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

        // C2-N-077 degrades an include directive under the comment profile even after expansion.
        self::assertStringContainsString('<p>{{ chapter }}</p>', $result->html);
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
