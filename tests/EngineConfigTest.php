<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
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

    public function testExternalLinksNofollowOption(): void
    {
        $entry = ['type' => 'external_links', 'nofollow' => true];
        $extension = (new ExtensionFactory())->create($entry);
        self::assertStringContainsString('nofollow', (new CarveRenderer(extensions: [$extension]))->render('[x](https://example.com)'));
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

    public function testSoftBreakMode(): void
    {
        $renderer = new CarveRenderer(new CarveConfig(softBreakMode: 'space'));
        self::assertStringContainsString('a b', $renderer->render("a\nb"));
        $positional = new CarveConfig(CarveProfile::Full, true, true);
        self::assertTrue($positional->sourceLines);
    }
}
