<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use MarkupCarve\Carve\Extension\ExtensionDefinition;
use MarkupCarve\Carve\Extension\Name;
use MarkupCarve\Tempest\CarveConfig;
use MarkupCarve\Tempest\CarveProfile;
use MarkupCarve\Tempest\CarveRenderer;
use MarkupCarve\Tempest\EngineExtensionFactory;
use MarkupCarve\Tempest\ExtensionFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EngineConfigTest extends TestCase
{
    /**
     * @return array<string, array{array<string, mixed>|string}>
     */
    public static function extensionNames(): array
    {
        $special = ['default_attributes' => ['type' => 'default_attributes', 'defaults' => ['paragraph' => ['class' => 'note']]], 'fenced_render' => ['type' => 'fenced_render', 'language' => 'custom']];
        $cases = [];
        foreach (EngineExtensionFactory::types() as $name) {
            $cases[$name] = [$special[$name] ?? $name];
        }

        return $cases;
    }

    /**
     * @param array<string, mixed>|string $entry
     */
    #[DataProvider('extensionNames')]
    public function testEveryRegisteredNameCanRender(array|string $entry): void
    {
        $factory = new ExtensionFactory();
        self::assertNotSame('', (new CarveRenderer(extensions: [$factory->create($entry)]))->render('hello'));
    }

    public function testHeadingLevelsDefaultsToOneLevel(): void
    {
        self::assertStringContainsString('<h2>Title</h2>', (new CarveRenderer(extensions: [(new ExtensionFactory())->create('heading_levels')]))->render("# Title\n"));
    }

    public function testHeadingLevelEntriesUseTheCurrentEngineOption(): void
    {
        $factory = new ExtensionFactory();
        $offset = $factory->create(['type' => 'heading_levels', 'offset' => 2]);
        $shift = $factory->create(['type' => 'heading_level_shift', 'shift' => 2]);

        self::assertStringContainsString('<h3>Title</h3>', (new CarveRenderer(extensions: [$offset]))->render("# Title\n"));
        self::assertStringContainsString('<h3>Title</h3>', (new CarveRenderer(extensions: [$shift]))->render("# Title\n"));
        self::assertSame("### Title\n", (new CarveRenderer(extensions: [$shift]))->renderMarkdown("# Title\n"));
    }

    public function testExternalLinksNofollowOption(): void
    {
        $entry = ['type' => 'external_links', 'nofollow' => true];
        $extension = (new ExtensionFactory())->create($entry);
        self::assertStringContainsString('nofollow', (new CarveRenderer(extensions: [$extension]))->render('[x](https://example.com)'));
    }

    public function testTableOfContentsUsesStandardRenderer(): void
    {
        $extension = (new ExtensionFactory())->create('table_of_contents');
        $result = CarveRenderer::safe(extensions: [$extension])->renderWithReport("= A\n\n== B\n");

        self::assertStringContainsString('<nav', $result->html);
        self::assertStringContainsString('<a href="#B">B</a>', $result->html);
        self::assertNotContains('extension-fallback-rendered', array_column($result->warnings, 'code'));
    }

    public function testTocPlacementUsesStandardRenderer(): void
    {
        $extension = (new ExtensionFactory())->create('toc_placement');
        $result = CarveRenderer::safe(extensions: [$extension])->renderWithReport("Intro.\n\n::: toc\n:::\n\n= A\n\n== B\n");

        self::assertStringContainsString('<nav', $result->html);
        self::assertStringContainsString('<a href="#B">B</a>', $result->html);
        self::assertNotContains('extension-fallback-rendered', array_column($result->warnings, 'code'));
    }

    public function testHostRendererOverridesStandardTocRenderer(): void
    {
        $extension = (new ExtensionFactory())->create('table_of_contents');
        $config = new CarveConfig(renderers: ['carve.toc' => static fn (): string => '<nav class="custom">Custom</nav>']);

        self::assertStringContainsString('<nav class="custom">Custom</nav>', CarveRenderer::safe($config, extensions: [$extension])->render("= A\n\n== B\n"));
    }

    public function testUnknownOptionIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ExtensionFactory())->create(['type' => 'heading_permalinks', 'misspelled' => true]);
    }

    public function testBundleDocumentTransformsRun(): void
    {
        $entry = ['type' => 'default_attributes', 'defaults' => ['paragraph' => ['class' => 'note']]];
        $factory = new ExtensionFactory();
        self::assertStringContainsString('class="note"', (new CarveRenderer(extensions: [$factory->create($entry)]))->render('hello'));
    }

    public function testDocumentTransformsFollowConfigurationOrder(): void
    {
        $factory = new ExtensionFactory();
        $defaults = $factory->create(['type' => 'default_attributes', 'defaults' => ['link' => ['class' => 'marked']]]);
        $permalinks = $factory->create('heading_permalinks');
        $before = (new CarveRenderer(extensions: [$defaults, $permalinks]))->render("# Hello\n");
        $after = (new CarveRenderer(extensions: [$permalinks, $defaults]))->render("# Hello\n");
        self::assertStringNotContainsString('permalink marked', $before);
        self::assertStringContainsString('permalink marked', $after);
    }

    public function testWikiOptionsAndPlantumlAlias(): void
    {
        $factory = new ExtensionFactory();
        $wiki = new CarveRenderer(extensions: [$factory->create(['type' => 'wikilinks', 'url_template' => '/wiki/{page}', 'css_class' => 'entry', 'new_window' => true])]);
        self::assertStringContainsString('class="entry"', $wiki->render(':wiki[Home Page]'));
        $generated = new CarveRenderer(extensions: [$factory->create(['type' => 'wikilinks', 'url_generator' => static fn (string $page): string => '/custom/' . $page, 'css_class' => 'custom'])]);
        self::assertStringContainsString('class="custom"', $generated->render(':wiki[Home]'));
        $puml = new CarveRenderer(extensions: [$factory->create('plantuml')]);
        self::assertStringContainsString('class="plantuml"', $puml->render("```puml\nA -> B\n```"));
        $legacy = new CarveRenderer(extensions: [$factory->create(['type' => 'plantuml', 'language' => 'puml'])]);
        self::assertStringContainsString('class="puml"', $legacy->render("```puml\nA -> B\n```"));
        $custom = new CarveRenderer(extensions: [$factory->create(['type' => 'mermaid', 'language' => 'custom', 'css_class' => 'diagram'])]);
        self::assertStringContainsString('class="diagram"', $custom->render("```custom\nA -> B\n```"));
    }

    public function testPlantumlFencesUseTheEngineNames(): void
    {
        $names = array_map(
            static fn (ExtensionDefinition $definition): string => $definition->name,
            array_filter((new EngineExtensionFactory())->create('plantuml'), static fn ($item): bool => $item instanceof ExtensionDefinition),
        );

        self::assertSame(['carve.plantuml.plantuml', 'carve.plantuml.puml'], array_values($names));
    }

    public function testNameCasesWorkLikeTheirStrings(): void
    {
        $factory = new ExtensionFactory();
        $source = ":::: tabs\n::: tab \"One\"\nA\n:::\n::: tab \"Two\"\nB\n:::\n::::\n";

        self::assertSame(
            (new CarveRenderer(extensions: [$factory->create('tabs')]))->render($source),
            (new CarveRenderer(extensions: [$factory->create(Name::Tabs)]))->render($source),
        );
        $aria = $factory->create(['type' => Name::Tabs, 'mode' => 'aria']);
        self::assertStringContainsString('role="tablist"', (new CarveRenderer(extensions: [$aria]))->render($source));
        self::assertSame($factory->fingerprint('tabs'), $factory->fingerprint(Name::Tabs));
        self::assertContains('heading_levels', EngineExtensionFactory::types());
        self::assertContains(Name::Wikilinks->value, EngineExtensionFactory::types());
    }

    public function testExternalLinksInternalHostsOption(): void
    {
        $extension = (new ExtensionFactory())->create(['type' => 'external_links', 'internal_hosts' => ['example.com']]);
        $html = (new CarveRenderer(extensions: [$extension]))->render('[x](https://example.com) [y](https://other.org)');

        self::assertStringContainsString('<a href="https://example.com">x</a>', $html);
        self::assertStringContainsString('<a href="https://other.org" rel="noopener noreferrer" target="_blank">y</a>', $html);
    }

    public function testSoftBreakMode(): void
    {
        $renderer = new CarveRenderer(new CarveConfig(softBreakMode: 'space'));
        self::assertStringContainsString('a b', $renderer->render("a\nb"));
        $positional = new CarveConfig(CarveProfile::Full, true, true);
        self::assertTrue($positional->sourceLines);
    }
}
