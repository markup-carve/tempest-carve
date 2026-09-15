# Configuration

The package discovers this safe default configuration:

```php
use MarkupCarve\Tempest\CarveConfig;

return new CarveConfig();
```

Create `config/carve.config.php` in the application to override it. Safe HTML
rendering remains enabled regardless of these options:

```php
use MarkupCarve\Carve\Extension\HeadingNumbersExtension;
use MarkupCarve\Carve\Renderer\SmartTypographyMode;
use MarkupCarve\Carve\Renderer\SoftBreakMode;
use MarkupCarve\Tempest\CarveConfig;
use MarkupCarve\Tempest\CarveProfile;
use Tempest\DateTime\Duration;

return new CarveConfig(
    profile: CarveProfile::Article,
    softBreakMode: SoftBreakMode::Space,
    smartTypography: SmartTypographyMode::Glyph,
    sourceLines: false,
    extensions: [HeadingNumbersExtension::class],
    cacheEnabled: true,
    cacheExpiration: Duration::hours(1),
    cacheKeySalt: 'site-rendering-v1',
);
```

## Profiles

`CarveProfile` exposes the four carve-php presets: `Full`, `Article`,
`Comment`, and `Minimal`. The default `null` profile allows the full Carve
vocabulary while safe mode still sanitizes HTML output. A custom carve-php
`Profile` instance may be supplied when a preset is not sufficient.

## Extensions

List extension class names in `extensions`. The Tempest container constructs
each extension, so constructor dependencies may be injected. The same configured
extensions apply to every output format.

## Cache

Caching is disabled by default. When enabled, the configured Tempest cache
stores each deterministic output by source content, output format, rendering
configuration, extension classes, and carve-php version. Set `cacheStore` to a
tagged Tempest cache name when the default project cache is not appropriate.
The cache fingerprint includes serializable profile and extension state. Set
`cacheKeySalt`, and change it when behavior changes, for custom profiles or
extensions containing non-serializable state such as closures. Cached rendering
rejects such state when no salt is configured instead of risking collisions.

Diagnostic reports bypass the cache so their warnings, profile violations, and
loss positions always describe the current source.

## Source lines

Set `sourceLines` to `true` to add 1-based `data-source-line` attributes to
rendered block elements. This is intended for editor preview synchronization
and is off by default to keep normal HTML output unchanged.
