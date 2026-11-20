# Configuration

The package discovers `CarveConfig` automatically. Create `config/carve.config.php` in the application to override it:

```php
use MarkupCarve\Tempest\CarveConfig;
use MarkupCarve\Tempest\CarveProfile;
use Tempest\DateTime\Duration;

return new CarveConfig(
    profile: CarveProfile::Article,
    smartTypography: true,
    sourceLines: false,
    headingIds: 'lower',
    mode: 'interactive',
    sections: true,
    rawHtml: false,
    extensions: ['tempest_highlight', 'tabs'],
    cacheEnabled: true,
    cacheExpiration: Duration::hours(1),
    cacheKeySalt: 'site-rendering',
    namedRenderers: [
        'comments' => new CarveConfig(profile: CarveProfile::Comment),
    ],
    includeResolver: App\Content\DatabaseIncludeResolver::class,
);
```

## Rendering policy

The default profile is `full`. `CarveProfile` offers `Full`, `Article`, `Comment`, and `Minimal`; a matching string is also accepted. Profiles constrain rendered content, while `rawHtml: false` and the engine's default `urlPolicy` keep the secure HTML policy. Set `rawHtml: true` only for trusted source. Pass a custom `urlPolicy` map when the default scheme policy is unsuitable. The engine validates the map.

`headingIds` accepts `off`, `keep-case`, `lower`, `ascii`, `ascii-lower`, `ascii-strict`, or `ascii-strict-lower`. `mode` accepts `interactive` or `static`. `sections` controls heading sections, `smartTypography` is a boolean, and `sourceLines` adds source line attributes. `softBreakMode` maps to the engine `softBreak` option (`newline`, `space`, or `br`).

A one-off profile can be passed to `render()`, `renderHtml()`, or `renderWithReport()`:

```php
$html = $carve->render($source, CarveProfile::Comment);
```

## Extensions

`extensions` accepts Carve `ExtensionDefinition` and `ExtensionProvider` objects, or their container-resolvable class names. This adapter also supplies the names `tabs`, `mermaid`, `plantuml` (including `puml` fences), `fenced_render` with a `language` option, and `tempest_highlight`. The names `lowercase_heading_ids` and `ascii_heading_ids` moved to `headingIds`. The engine bundle names are available through `EngineExtensionFactory::types()`. Option arrays use a `type` and snake case factory parameter names. `wikilinks` accepts `url_template`, `url_generator`, `css_class`, and `new_window`. Unknown names and options fail at startup.

`writers` accepts Carve `OutputWriter` instances. The engine ships `html`, `carve`, `text`, `markdown`, and `ansi`. Register custom writers only for additional targets.

## Cache and includes

Caching is off by default. A cache key covers source, target, profile, rendering policy, extensions, writers, and `cacheKeySalt`. A custom extension or writer with nonserializable state requires an explicit salt. Change the salt when its behavior changes.

`includeResolver` is a container-resolvable Carve `IncludeResolver`. It receives a request with `path`, `from`, `options`, `section`, and `budget`, then returns `['text' => $source, 'id' => $stableId]` or `['error' => 'not_found']` and the other documented causes. All parsing and rendering use the 0.2 include resolver. Include output is cached only when the resolver also implements `IncludeCacheKeyProvider`. `IncludeOptions` can narrow depth and byte limits; Carve does not expose the old resolver-call and warning limits yet, and nondefault values fail explicitly.
