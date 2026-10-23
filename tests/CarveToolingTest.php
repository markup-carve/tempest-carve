<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use MarkupCarve\Carve\Extension\LowercaseHeadingIdsExtension;
use MarkupCarve\Carve\Extension\WikilinksExtension;
use MarkupCarve\Carve\Lint\LintWarning;
use MarkupCarve\Carve\Transform\FilesystemIncludeResolver;
use MarkupCarve\Tempest\CarveConfig;
use MarkupCarve\Tempest\CarveRenderer;
use MarkupCarve\Tempest\ExtensionFactory;
use MarkupCarve\Tempest\Highlight\TempestHighlightExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Tempest\Cache\GenericCache;

final class CarveToolingTest extends TestCase
{
    #[Test]
    public function testLintReportsTheRulesOfTheDocumentLinter(): void
    {
        $renderer = CarveRenderer::safe();

        self::assertContains('markdown-strong-double-star', self::rules($renderer->lint("**bold**\n")));
        self::assertContains('unattached-block-attribute', self::rules($renderer->lint("{.a}\n\n")));
        self::assertContains('list-marker-below-content-column', self::rules($renderer->lint("9. a\n   - b\n10. c\n   - d\n")));
        self::assertContains('consumed-key-near-miss', self::rules($renderer->lint("{lose}\n- a\n- b\n")));
        self::assertSame([], $renderer->lint("# Fine\n\n*strong*\n"));
    }

    #[Test]
    public function testLintDelegatesToTheEngineLinters(): void
    {
        $renderer = (string)file_get_contents((string)(new ReflectionClass(CarveRenderer::class))->getFileName());

        self::assertStringContainsString('new DocumentLinter()', $renderer);
        self::assertStringContainsString('new IncludeLinter()', $renderer);
    }

    #[Test]
    public function testLintJudgesFragmentLinksAgainstTheConfiguredExtensions(): void
    {
        $source = "# Alpha\n\nSee [a](#alpha).\n";

        self::assertContains('broken-fragment-link', self::rules(CarveRenderer::safe()->lint($source)));
        self::assertSame([], CarveRenderer::safe(extensions: [new LowercaseHeadingIdsExtension()])->lint($source));
    }

    #[Test]
    public function testLintIncludedReportsFindingsFromIncludedFiles(): void
    {
        $root = __DIR__ . '/Fixtures/includes';
        $source = (string)file_get_contents($root . '/main.crv');

        self::assertSame([], CarveRenderer::safe()->lint($source));
        $warnings = CarveRenderer::safe()->lintIncluded($source, $root . '/main.crv', new FilesystemIncludeResolver($root));
        self::assertSame(['markdown-strong-double-star'], self::rules($warnings));
        self::assertStringEndsWith('part.crv', (string)$warnings[0]->file);
    }

    #[Test]
    public function testLintIncludedUsesTheConfiguredResolver(): void
    {
        $root = __DIR__ . '/Fixtures/includes';
        $renderer = CarveRenderer::safe(includeResolver: new FilesystemIncludeResolver($root));

        self::assertSame(
            ['markdown-strong-double-star'],
            self::rules($renderer->lintIncluded((string)file_get_contents($root . '/main.crv'), $root . '/main.crv')),
        );
    }

    #[Test]
    public function testLintIncludedRequiresAResolver(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CarveRenderer::safe()->lintIncluded('text');
    }

    #[Test]
    public function testFormatReturnsCanonicalSourceAndAlignsTablesOnRequest(): void
    {
        $renderer = CarveRenderer::safe();
        $table = "| a | bb |\n|---|---|\n| ccc | d |\n";

        self::assertSame("# T\n\ntext\n", $renderer->format("# T\n\ntext  \n"));
        self::assertSame($table, $renderer->format($table));
        self::assertSame("| a   | bb |\n|---  |--- |\n| ccc | d  |\n", $renderer->format($table, alignTables: true));
    }

    #[Test]
    public function testImportsMarkdownAndHtml(): void
    {
        $renderer = CarveRenderer::safe();

        self::assertSame("# Hi\n\n*Bold* [link](https://example.com)\n", $renderer->fromMarkdown("# Hi\n\n**Bold** [link](https://example.com)\n"));
        self::assertSame("# Hi\n\n*Bold*\n", $renderer->fromHtml('<h1>Hi</h1><p><strong>Bold</strong></p>'));
    }

    #[Test]
    public function testExtensionFactoryBuildsExtensionsByName(): void
    {
        $factory = new ExtensionFactory();

        self::assertInstanceOf(LowercaseHeadingIdsExtension::class, $factory->create('lowercase_heading_ids'));
        self::assertInstanceOf(TempestHighlightExtension::class, $factory->create('tempest_highlight'));

        $html = CarveRenderer::safe(extensions: [$factory->create('mermaid')])->render("``` mermaid\ngraph TD\n```\n");
        self::assertStringContainsString('class="mermaid"', $html);
    }

    #[Test]
    public function testWikiLinksTakeAUrlTemplate(): void
    {
        $extension = (new ExtensionFactory())->create([
            'type' => 'wikilinks',
            'url_template' => '/wiki/{page}',
            'link_class' => 'wiki',
        ]);
        self::assertInstanceOf(WikilinksExtension::class, $extension);

        $html = CarveRenderer::safe(extensions: [$extension])->render('See [[Tiger Facts]].');

        self::assertStringContainsString('href="/wiki/tiger-facts"', $html);
        self::assertStringContainsString('class="wiki"', $html);
    }

    #[Test]
    public function testExtensionFactoryRejectsUnknownTypes(): void
    {
        $this->expectExceptionMessage('Unknown Carve extension "no_such_thing"');

        (new ExtensionFactory())->create('no_such_thing');
    }

    #[Test]
    public function testExtensionFactoryRejectsUnknownOptions(): void
    {
        $this->expectExceptionMessage('Unknown option "bogus" for Carve extension "wikilinks"');

        (new ExtensionFactory())->create(['type' => 'wikilinks', 'bogus' => true]);
    }

    #[Test]
    public function testConfigEntriesKeepTheCacheApart(): void
    {
        $factory = new ExtensionFactory();
        $cache = new GenericCache(new ArrayAdapter());
        $config = new CarveConfig(cacheEnabled: true);
        $first = ['type' => 'wikilinks', 'url_template' => '/a/{page}'];
        $second = ['type' => 'wikilinks', 'url_template' => '/b/{page}'];

        $a = new CarveRenderer($config, $cache, [$factory->create($first)], extensionKeys: [0 => (string)$factory->fingerprint($first)]);
        $b = new CarveRenderer($config, $cache, [$factory->create($second)], extensionKeys: [0 => (string)$factory->fingerprint($second)]);

        self::assertStringContainsString('href="/a/tiger"', $a->render('[[Tiger]]'));
        self::assertStringContainsString('href="/b/tiger"', $b->render('[[Tiger]]'));
    }

    #[Test]
    public function testEntriesWithCallablesAreNotFingerprinted(): void
    {
        $factory = new ExtensionFactory();

        self::assertNull($factory->fingerprint(['type' => 'wikilinks', 'url_generator' => static fn (string $page): string => $page]));
        self::assertNotNull($factory->fingerprint(['type' => 'wikilinks', 'url_template' => '/a/{page}']));
    }

    #[Test]
    public function testHighlightsFencedCodeInHtmlOnly(): void
    {
        $renderer = CarveRenderer::safe(extensions: [new TempestHighlightExtension()]);
        $source = "``` php\n<?php echo \"<b>\";\n```\n";

        $html = $renderer->render($source);
        self::assertStringContainsString('<pre><code class="language-php"><span class="hl-keyword">&lt;?php</span>', $html);
        self::assertStringNotContainsString('<b>', $html);
        self::assertSame("```php\n<?php echo \"<b>\";\n```\n", $renderer->renderMarkdown($source));
    }

    #[Test]
    public function testHighlightsCarveFencesThroughTempestHighlightCarve(): void
    {
        $html = CarveRenderer::safe(extensions: [new TempestHighlightExtension()])->render("``` carve\n# Hi\n```\n");

        self::assertStringContainsString('<span class="hl-keyword"># Hi</span>', $html);
    }

    #[Test]
    public function testLeavesUnknownLanguagesAndAttributedFencesAlone(): void
    {
        $renderer = CarveRenderer::safe(extensions: [new TempestHighlightExtension()]);

        self::assertSame("<pre><code class=\"language-nope\">x&lt;y\n</code></pre>\n", $renderer->render("``` nope\nx<y\n```\n"));
        self::assertStringNotContainsString('hl-', $renderer->render("{#snippet}\n``` php\n\$a = 1;\n```\n"));
    }

    /**
     * @param list<\MarkupCarve\Carve\Lint\LintWarning> $warnings
     *
     * @return list<string>
     */
    private static function rules(array $warnings): array
    {
        return array_map(static fn (LintWarning $warning): string => $warning->rule, $warnings);
    }
}
