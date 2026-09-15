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
