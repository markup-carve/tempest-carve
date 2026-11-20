# Usage

Pass Carve source from a controller or view object into a Tempest view:

```php
return view('article.view.php', document: $article->body);
```

Render it with the package component:

```html
<article>
    <x-carve :content="$document" />
</article>
```

The package registers `MarkupCarve\Tempest\CarveRenderer` as a singleton, so it
can also be injected directly:

```php
use MarkupCarve\Tempest\CarveRenderer;

final readonly class RenderExcerpt
{
    public function __construct(private CarveRenderer $carve) {}

    public function __invoke(string $source): string
    {
        return $this->carve->render($source);
    }
}
```

Run `php tempest discovery:generate --no-interaction` after installing or
updating the package when the application uses a discovery cache.

## Output formats

`render()` and `renderHtml()` return safe HTML. The service retains `renderMarkdown()`, `renderPlainText()`, and `renderAnsi()` and the matching console choices. They use Carve's writer registry. The engine supplies the corresponding writers. The view component always uses HTML.

Register a custom writer in `CarveConfig::writers` when needed:

```php
return new CarveConfig(writers: [new App\Carve\TextWriter()]);
```

The writer targets are `markdown`, `text`, and `ansi`. Carve ships all five targets by default.

## Diagnostics

Use `renderWithReport()` when an authoring interface needs more than rendered
HTML:

```php
$report = $carve->renderWithReport($source);

$report->html;
$report->warnings;
$report->profileViolations;
$report->losses;
$report->totalLosses;
$report->lossesTruncated;
```

Warnings contain source line and column information. Losses report source that
the HTML target cannot represent, and profile violations explain content that
the selected profile removed or degraded. Loss collection is bounded to 100
entries by default and can be changed with the third method argument.
Report rendering bypasses the output cache so its diagnostics always describe
the current source.

## Includes

Configure a Carve `IncludeResolver` in `CarveConfig::includeResolver`, then call `renderIncluded()` with an optional `IncludeOptions` value. The result carries HTML, diagnostics, and dependencies. `suppressedWarnings` is currently null because Carve does not expose that count. Depth and byte limits may be narrowed; nondefault resolver-call and warning limits fail explicitly until the engine supplies them.

## Lint, format and import

```php
$findings = $carve->lint($source);
$includedFindings = $carve->lintIncluded($source, $path);
$canonical = $carve->format($source, alignTables: true);
$fromHtml = $carve->fromHtml($html);
```

Each lint finding has `rule`, `message`, `file`, `line`, `column`, and the underlying Carve `diagnostic`. HTML import uses Carve's importer. `fromMarkdown()` imports GitHub Flavored Markdown, as in 0.1: tables, task lists and strikethrough convert.

## Console commands

| Command | Does |
| --- | --- |
| `carve:render <path> [--format=html\|text\|markdown\|ansi] [--renderer=name] [--output=file]` | Renders a file |
| `carve:lint <path> [--include-root=dir]` | Lints a file or every `*.crv` below a directory; exits 1 on findings |
| `carve:fmt <path> [--write\|--check] [--align-tables]` | Prints, rewrites or checks canonical form |
| `carve:convert <path> [--from=markdown\|gfm\|html] [--output=file]` | Converts HTML or GitHub Flavored Markdown to Carve |

Commands use the discovered `CarveRenderer`, so configured extensions apply.
Tempest matches positional arguments by position, so put the path before the
flags.

## Validation

`ValidCarve` is a Tempest validation rule:

```php
use MarkupCarve\Tempest\CarveProfile;
use MarkupCarve\Tempest\Validation\ValidCarve;

final class CommentRequest
{
    #[ValidCarve(profile: CarveProfile::Comment, lint: true, maxLength: 5000)]
    public string $body;
}
```

It fails on invalid input, on markup the profile does not allow, on parse
diagnostics with `strict: true`, and on lint findings with `lint: true`. `null`
and the empty string pass. `message` takes a template where `{error}` is
replaced with the reason.

## Model properties

Type a property as `CarveContent` to store Carve source and read it back
rendered:

```php
use MarkupCarve\Tempest\Mapper\CarveContent;

final class Post
{
    public CarveContent $body;
}
```

```html
{!! $post->body !!}
```

The column holds the source string. `$post->body->source` is the stored
markup, `$post->body->html()` renders it with the discovered renderer, and
`$post->body->html('comment')` uses a named renderer. Rendering happens on
first access and is memoized per renderer name. A plain string assigned to
the property is cast on mapping.

## Code highlighting

The `tempest_highlight` extension highlights fenced code in HTML output with
[tempest/highlight](https://github.com/tempestphp/highlight):

```sh
composer require tempest/highlight markup-carve/tempest-highlight-carve
```

With [tempest-highlight-carve](https://github.com/markup-carve/tempest-highlight-carve)
installed, `carve` and `crv` fences are highlighted too. Fences in a language
the highlighter does not know, and fences with attributes or a label, keep the
default markup. Style the output with a tempest/highlight theme. Pass a
configured `Highlighter` to `new TempestHighlightExtension($highlighter)` to
add languages or change the theme.

## Test assertions

PHPUnit test cases may use `MarkupCarve\Tempest\Testing\CarveAssertions` for
focused rendering, safety, and warning assertions:

```php
use MarkupCarve\Tempest\Testing\CarveAssertions;

final class ArticleTest extends TestCase
{
    use CarveAssertions;
}
```

`assertCarveIsSafe()` checks that a specific unsafe substring is absent; it is a
focused regression assertion, not a general-purpose HTML security scanner.
