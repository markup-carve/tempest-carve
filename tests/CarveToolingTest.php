<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use MarkupCarve\Carve\Extension\ExtensionDefinition;
use MarkupCarve\Carve\Include\FileResolver;
use MarkupCarve\Tempest\CarveConfig;
use MarkupCarve\Tempest\CarveRenderer;
use MarkupCarve\Tempest\ExtensionFactory;
use MarkupCarve\Tempest\Highlight\TempestHighlightExtension;
use MarkupCarve\Tempest\LintFinding;
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
        // C2-W-106 uses the renamed list hint and the 0.2 consumed table key.
        self::assertContains('list-item-body-below-content-column', self::rules($renderer->lint("9. a\n   - b\n10. c\n   - d\n")));
        self::assertContains('consumed-key-near-miss', self::rules($renderer->lint("{tabel}\n```csv\na,b\n```\n")));
        self::assertSame([], $renderer->lint("# Fine\n\n*strong*\n"));
    }

    #[Test]
    public function testLintHonorsSuppressionComments(): void
    {
        $renderer = CarveRenderer::safe();

        self::assertSame([], $renderer->lint("%% carve-lint-disable-next-line markdown-strong-double-star\n**bold**\n"));
        self::assertSame(['markdown-strong-double-star'], self::rules($renderer->lint("%% carve-lint-disable-next-line heading-level-skip\n**bold**\n")));
    }

    #[Test]
    public function testLintDelegatesToTheEngineLinters(): void
    {
        $renderer = (string)file_get_contents((string)(new ReflectionClass(CarveRenderer::class))->getFileName());

        // C2-T-005 provides the public Carve lint call for both paths.
        self::assertStringContainsString('Carve::lint($source', $renderer);
        self::assertStringContainsString("'includes' =>", $renderer);
    }

    #[Test]
    public function testLintJudgesFragmentLinksAgainstTheConfiguredExtensions(): void
    {
        $source = "# Alpha\n\nSee [a](#alpha).\n";

        self::assertContains('broken-fragment-link', self::rules(CarveRenderer::safe()->lint($source)));
        // C2-H-131 moves lowercase ids from an extension to headingIds.
        self::assertSame([], CarveRenderer::safe(new CarveConfig(headingIds: 'lower'))->lint($source));
    }

    #[Test]
    public function testLintIncludedReportsFindingsFromIncludedFiles(): void
    {
        $root = __DIR__ . '/Fixtures/includes';
        $source = (string)file_get_contents($root . '/main.crv');

        // C2-N-085 reports a missing resolver before linting included files.
        self::assertSame(['include-no-resolver'], self::rules(CarveRenderer::safe()->lint($source)));
        $warnings = CarveRenderer::safe()->lintIncluded($source, $root . '/main.crv', FileResolver::forRoot($root));
        self::assertSame(['markdown-strong-double-star'], self::rules($warnings));
        self::assertStringEndsWith('part.crv', (string)$warnings[0]->file);
    }

    #[Test]
    public function testLintIncludedUsesTheConfiguredResolver(): void
    {
        $root = __DIR__ . '/Fixtures/includes';
        $renderer = CarveRenderer::safe(includeResolver: FileResolver::forRoot($root));

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
        // U-055 replaces GFM separator rows with native header cells.
        $table = "|= a |= bb |\n| ccc | d |\n";

        // C2-W-100 writes the 0.2 canonical heading spelling.
        self::assertSame("= T\n\ntext\n", $renderer->format("# T\n\ntext  \n"));
        self::assertSame("|= a |= bb |\n| ccc | d |\n", $renderer->format($table));
        self::assertSame("|= a  |= bb |\n| ccc | d   |\n", $renderer->format($table, alignTables: true));
        // C2-B-159 reads a hyphen run as text, not a thematic break.
        self::assertSame("a\n\n---\n\nb\n", $renderer->format("a\n\n---\n\nb\n"));
    }

    #[Test]
    public function testImportsMarkdownAndHtml(): void
    {
        $renderer = CarveRenderer::safe();

        self::assertSame("= Hi\n\n*Bold* [link](https://example.com)\n", $renderer->fromMarkdown("# Hi\n\n**Bold** [link](https://example.com)\n"));
        self::assertSame("= Hi\n\n*Bold*\n", $renderer->fromHtml('<h1>Hi</h1><p><strong>Bold</strong></p>'));
        self::assertSame(
            "|= a |={>} b |\n| 1 | 2 |\n\n- [x] done\n",
            $renderer->fromMarkdown("| a | b |\n|---|--:|\n| 1 | 2 |\n\n- [x] done\n"),
        );
    }

    #[Test]
    public function testExtensionFactoryBuildsExtensionsByName(): void
    {
        $factory = new ExtensionFactory();

        // C2-X-002 uses extension definitions; heading ids moved to C2-H-131.
        self::assertInstanceOf(ExtensionDefinition::class, $factory->create('mermaid'));
        self::assertInstanceOf(TempestHighlightExtension::class, $factory->create('tempest_highlight'));

        $html = CarveRenderer::safe(extensions: [$factory->create('mermaid')])->render("``` mermaid\ngraph TD\n```\n");
        self::assertStringContainsString('class="mermaid"', $html);
    }

    #[Test]
    public function testExtensionFactoryRejectsUnknownTypes(): void
    {
        // C2-X-002 rejects unknown 0.2 registrations.
        $this->expectExceptionMessage('Unknown Carve extension "no_such_thing"');

        (new ExtensionFactory())->create('no_such_thing');
    }

    #[Test]
    public function testExtensionFactoryRejectsUnknownOptions(): void
    {
        // C2-X-002 validates options on a supported 0.2 registration.
        $this->expectExceptionMessage('Unknown option "bogus" for Carve extension "mermaid"');

        (new ExtensionFactory())->create(['type' => 'mermaid', 'bogus' => true]);
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

        self::assertStringContainsString('href="/a/tiger"', $a->render(':wiki[Tiger]'));
        self::assertStringContainsString('href="/b/tiger"', $b->render(':wiki[Tiger]'));
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
        self::assertSame(str_replace('``` php', '```php', $source), $renderer->renderMarkdown($source));
    }

    #[Test]
    public function testHighlightsCarveFencesThroughTempestHighlightCarve(): void
    {
        $html = CarveRenderer::safe(extensions: [new TempestHighlightExtension()])->render("``` carve\n# Hi\n```\n");

        self::assertStringContainsString('<span class="hl-keyword"># Hi</span>', $html);
    }

    #[Test]
    public function testLeavesUnknownLanguagesAloneAndHighlightsAttributedFences(): void
    {
        $renderer = CarveRenderer::safe(extensions: [new TempestHighlightExtension()]);

        self::assertSame("<pre><code class=\"language-nope\">x&lt;y\n</code></pre>", $renderer->render("``` nope\nx<y\n```\n"));
        self::assertStringContainsString('hl-', $renderer->render("{#snippet}\n``` php\n\$a = 1;\n```\n"));
    }

    #[Test]
    public function testHighlightsCodeWithLineOptionsAndTitle(): void
    {
        $renderer = CarveRenderer::safe(extensions: [new TempestHighlightExtension()]);
        $lineOptions = $renderer->render("{.line-numbers data-line-start=3}\n```php\n\$a = 1;\n```\n");
        self::assertStringContainsString('class="line-numbers"', $lineOptions);
        self::assertStringContainsString('data-line-start="3"', $lineOptions);
        self::assertStringContainsString('<span class="hl-', $lineOptions);

        $titled = $renderer->render("```php \"example.php\"\n\$a = 1;\n```\n");
        self::assertStringContainsString('<pre title="example.php">', $titled);
        self::assertStringContainsString('<span class="hl-', $titled);
    }

    #[Test]
    public function testCodeLineOptionsNumberAndHighlightPlainAndCaptionedBlocks(): void
    {
        $renderer = CarveRenderer::safe(extensions: [new TempestHighlightExtension()]);
        $plain = $renderer->render("{.line-numbers data-line-start=42 data-highlight=1,3-5}\n```php\n\$a = 1;\n\$b = 2;\n```\n");
        self::assertStringContainsString('<span class="hl-gutter">42</span>', $plain);
        self::assertStringContainsString('<span class="hl-gutter">43</span>', $plain);
        self::assertSame(1, substr_count($plain, 'class="hl-line-highlighted"'));
        self::assertStringContainsString('class="hl-variable"', $plain);

        $captioned = $renderer->render("{.line-numbers data-line-start=7 data-highlight=2}\n```php\n\$a = 1;\n\$b = 2;\n```\n^ The app.\n");
        self::assertStringContainsString('<figure>', $captioned);
        self::assertStringContainsString('<pre class="line-numbers" data-line-start="7" data-highlight="2">', $captioned);
        self::assertStringContainsString('<figcaption>The app.</figcaption>', $captioned);
        self::assertStringContainsString('<span class="hl-gutter">8</span>', $captioned);
        self::assertSame(1, substr_count($captioned, 'class="hl-line-highlighted"'));
    }

    #[Test]
    public function testInvalidCodeLineOptionsFallBackAndKeepTokenSpans(): void
    {
        $renderer = CarveRenderer::safe(extensions: [new TempestHighlightExtension()]);
        $invalid = $renderer->render("{.line-numbers data-line-start=auto data-highlight=\"1, 2\"}\n```php\n\$a = 1;\n\$b = 2;\n```\n");
        self::assertStringContainsString('<span class="hl-gutter">1</span>', $invalid);
        self::assertStringNotContainsString('hl-line-highlighted', $invalid);

        $multiline = $renderer->render("{data-highlight=2}\n```php\n/* a\nb */\n```\n");
        self::assertStringContainsString('<span class="hl-comment">/* a</span>', $multiline);
        self::assertStringContainsString('<span class="hl-comment">b */</span>', $multiline);
        self::assertSame(1, substr_count($multiline, 'class="hl-line-highlighted"'));
    }

    #[Test]
    public function testLargeLineStartKeepsExactDecimalNumbers(): void
    {
        $renderer = CarveRenderer::safe(extensions: [new TempestHighlightExtension()]);
        $html = $renderer->render("{.line-numbers data-line-start=9223372036854775807}\n```php\na\nb\n```\n");

        self::assertStringContainsString('<span class="hl-gutter">9223372036854775807</span>', $html);
        self::assertStringContainsString('<span class="hl-gutter">9223372036854775808</span>', $html);
    }

    #[Test]
    public function testLeadingZeroHighlightValueSelectsNoLine(): void
    {
        $renderer = CarveRenderer::safe(extensions: [new TempestHighlightExtension()]);
        $html = $renderer->render("{data-highlight=01}\n```php\necho 1;\n```\n");

        self::assertStringNotContainsString('hl-line-highlighted', $html);
    }

    /**
     * @param list<\MarkupCarve\Tempest\LintFinding> $warnings
     *
     * @return list<string>
     */
    private static function rules(array $warnings): array
    {
        return array_map(static fn (LintFinding $warning): string => $warning->rule, $warnings);
    }
}
