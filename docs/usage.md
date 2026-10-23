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

`render()` and `renderHtml()` return safe HTML. The service also exposes
Markdown, plain-text, and ANSI targets:

```php
$html = $carve->renderHtml($source);
$markdown = $carve->renderMarkdown($source);
$plainText = $carve->renderPlainText($source);
$ansi = $carve->renderAnsi($source);
```

The view component always uses HTML. Other formats are service APIs for uses
such as search indexes, excerpts, mail pipelines, and console output.
Only HTML is sanitized as HTML. Markdown escapes raw HTML, plain text omits it,
and ANSI represents it as terminal text; callers must still treat every output
according to its target format.

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

After configuring an include resolver, render a composed document with bounded
expansion options:

```php
use MarkupCarve\Tempest\IncludeOptions;

$result = $carve->renderIncluded(
    $source,
    new IncludeOptions(currentPath: 'articles/handbook.crv'),
);

$result->html;
$result->warnings;
$result->dependencies;
$result->suppressedWarnings;
```

The dependency list contains canonical targets and resolution status, making it
suitable for preview invalidation and build tooling.

## Lint, format and import

```php
$warnings = $carve->lint($source);
foreach ($warnings as $warning) {
    echo "{$warning->line}:{$warning->column} {$warning->rule} {$warning->message}\n";
}

$warnings = $carve->lintIncluded($source, $path); // also lints included files
$canonical = $carve->format($source, alignTables: true);
$fromMarkdown = $carve->fromMarkdown($markdown);
$fromHtml = $carve->fromHtml($html);
```

`lint()` runs every rule of the engine's `DocumentLinter` and passes the
configured extensions on, so a fragment link to a heading id an extension
produces is not reported as broken. `lintIncluded()` uses the configured
include resolver unless one is passed; findings from an included file carry
`file` and `includedBy`. `format()` returns the canonical source `carve fmt`
prints, with table columns padded when `alignTables` is set.

## Console commands

| Command | Does |
| --- | --- |
| `carve:render <path> [--format=html\|text\|markdown\|ansi] [--renderer=name] [--output=file]` | Renders a file |
| `carve:lint <path> [--include-root=dir]` | Lints a file or every `*.crv` below a directory; exits 1 on findings |
| `carve:fmt <path> [--write\|--check] [--align-tables]` | Prints, rewrites or checks canonical form |
| `carve:convert <path> [--from=markdown\|html] [--output=file]` | Converts Markdown or HTML to Carve; the format is guessed from the extension |

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

It fails on a parse error, on markup the profile does not allow, on parse
warnings with `strict: true`, and on lint findings with `lint: true`. `null`
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
