<?php

declare(strict_types=1);

namespace Tests;

use MarkupCarve\Tempest\CarveConfig;
use MarkupCarve\Tempest\CarveProfile;
use MarkupCarve\Tempest\CarveRenderer;
use MarkupCarve\Tempest\Mapper\CarveContent;
use PHPUnit\Framework\Attributes\Test;
use Tempest\Validation\Validator;
use Tests\Fixtures\Post;
use function Tempest\Mapper\map;

final class CarveIntegrationToolingTest extends IntegrationTestCase
{
    private string $files = __DIR__ . '/Fixtures/files';

    #[Test]
    public function testRenderCommandPrintsEachFormat(): void
    {
        $this->console->call('carve:render ' . $this->files . '/table.crv')
            ->assertSuccess()
            ->assertContains('<h1>Title</h1>');
        $this->console->call('carve:render ' . $this->files . '/table.crv --format=markdown')
            ->assertSuccess()
            ->assertContains('| ccc | d |');
        $this->console->call('carve:render ' . $this->files . '/table.crv --format=pdf')
            ->assertError()
            ->assertContains('Unknown format [pdf]');
        $this->console->call('carve:render ' . $this->files . '/missing.crv')
            ->assertError()
            ->assertContains('is not readable');
    }

    #[Test]
    public function testRenderCommandWritesToAFile(): void
    {
        $target = sys_get_temp_dir() . '/tempest-carve-render-' . bin2hex(random_bytes(6)) . '.html';

        $this->console->call('carve:render ' . $this->files . '/table.crv --output=' . $target)->assertSuccess();

        self::assertStringContainsString('<h1>Title</h1>', (string)file_get_contents($target));
    }

    #[Test]
    public function testLintCommandReportsFindings(): void
    {
        $this->console->call('carve:lint ' . $this->files . '/habit.crv')
            ->assertError()
            ->assertContains('habit.crv:3:6  markdown-strong-double-star')
            ->assertContains('1 Carve problem(s) found.');
        $this->console->call('carve:lint ' . $this->files . '/table.crv')
            ->assertSuccess()
            ->assertContains('No Carve problems found.');
        $this->console->call('carve:lint ' . $this->files)
            ->assertError()
            ->assertContains('habit.crv');
    }

    #[Test]
    public function testLintCommandFollowsIncludesBelowARoot(): void
    {
        $root = __DIR__ . '/Fixtures/includes';

        $this->console->call('carve:lint ' . $root . '/main.crv')->assertSuccess();
        $this->console->call('carve:lint ' . $root . '/main.crv --include-root=' . $root)
            ->assertError()
            ->assertContains('part.crv:1:1  markdown-strong-double-star');
        $this->console->call('carve:lint ' . $root . '/main.crv --include-root=' . $root . '/missing')
            ->assertError()
            ->assertContains('is not a directory');
    }

    #[Test]
    public function testFmtCommandPrintsChecksAndAlignsTables(): void
    {
        $this->console->call('carve:fmt ' . $this->files . '/table.crv --align-tables')
            ->assertSuccess()
            ->assertContains("| a   | bb |\n|---  |--- |\n| ccc | d  |");
        $this->console->call('carve:fmt ' . $this->files . '/table.crv --check')->assertSuccess();
        $this->console->call('carve:fmt ' . $this->files . '/table.crv --check --align-tables')
            ->assertError()
            ->assertContains('table.crv is not formatted');
        $this->console->call('carve:fmt ' . $this->files . '/table.crv --check --write')->assertInvalid();
    }

    #[Test]
    public function testFmtCommandRewritesFilesInPlace(): void
    {
        $file = sys_get_temp_dir() . '/tempest-carve-fmt-' . bin2hex(random_bytes(6)) . '.crv';
        file_put_contents($file, "# T\n\ntext  \n");

        $this->console->call('carve:fmt ' . $file . ' --write')->assertSuccess();

        self::assertSame("# T\n\ntext\n", file_get_contents($file));
    }

    #[Test]
    public function testConvertCommandImportsMarkdownAndHtml(): void
    {
        $this->console->call('carve:convert ' . $this->files . '/page.md')
            ->assertSuccess()
            ->assertContains('*Bold* [link](https://example.com)');
        $this->console->call('carve:convert ' . $this->files . '/page.html')
            ->assertSuccess()
            ->assertContains('*Bold*');
        $this->console->call('carve:convert ' . $this->files . '/table.crv')
            ->assertInvalid()
            ->assertContains('Cannot tell the input format');
        $this->console->call('carve:convert ' . $this->files . '/table.crv --from=markdown')->assertSuccess();
    }

    #[Test]
    public function testCommandsUseTheConfiguredRenderer(): void
    {
        $this->container->config(new CarveConfig(extensions: [['type' => 'wikilinks', 'url_template' => '/w/{page}']]));
        $file = sys_get_temp_dir() . '/tempest-carve-wiki-' . bin2hex(random_bytes(6)) . '.crv';
        file_put_contents($file, "[[Tiger Facts]]\n");

        $this->console->call('carve:render ' . $file)
            ->assertSuccess()
            ->assertContains('href="/w/tiger-facts"');
    }

    #[Test]
    public function testInitializerBuildsExtensionsConfiguredByName(): void
    {
        $this->container->config(new CarveConfig(
            extensions: ['lowercase_heading_ids', ['type' => 'wikilinks', 'url_template' => '/docs/{page}']],
            cacheEnabled: true,
        ));
        $this->cache->fake();

        $renderer = $this->container->get(CarveRenderer::class);

        self::assertStringContainsString('href="/docs/tiger"', $renderer->render('[[Tiger]]'));
        self::assertStringContainsString('id="alpha"', $renderer->render('# Alpha'));
    }

    #[Test]
    public function testCasterAndSerializerRoundTripCarveContent(): void
    {
        $post = map(['title' => 'Hello', 'body' => "# Hi\n\n/there/"])->to(Post::class);
        self::assertInstanceOf(Post::class, $post);

        self::assertInstanceOf(CarveContent::class, $post->body);
        self::assertSame("# Hi\n\n/there/", $post->body->source);
        self::assertStringContainsString('<em>there</em>', (string)$post->body);
        self::assertSame(['title' => 'Hello', 'body' => "# Hi\n\n/there/"], map($post)->toArray());
    }

    #[Test]
    public function testCarveContentRendersThroughANamedRenderer(): void
    {
        $this->container->config(new CarveConfig(namedRenderers: [
            'comment' => new CarveConfig(profile: CarveProfile::Minimal),
        ]));

        $post = map(['title' => 'Hello', 'body' => "# Heading\n\n/text/"])->to(Post::class);
        self::assertInstanceOf(Post::class, $post);

        self::assertStringContainsString('<h1>Heading</h1>', $post->body->html());
        self::assertStringNotContainsString('<h1>', $post->body->html('comment'));
    }

    #[Test]
    public function testValidCarveRuleRunsThroughTheValidator(): void
    {
        $validator = $this->container->get(Validator::class);

        self::assertSame([], $validator->validateValuesForClass(Post::class, ['title' => 'x', 'body' => '/fine/']));

        $failures = $validator->validateValuesForClass(Post::class, ['title' => 'x', 'body' => "**bold**\n"]);
        self::assertArrayHasKey('body', $failures);
        self::assertStringContainsString(
            'renders as literal asterisks',
            $validator->getErrorMessage($failures['body'][0]),
        );
    }
}
