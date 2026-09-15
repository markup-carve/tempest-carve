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
