# Carve for Tempest

[![CI](https://github.com/markup-carve/tempest-carve/actions/workflows/ci.yml/badge.svg)](https://github.com/markup-carve/tempest-carve/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-%5E8.5-777bb4)](composer.json)
[![Tempest](https://img.shields.io/badge/Tempest-%5E3.19-1a1a1a)](https://tempestphp.com/)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%209-brightgreen.svg?style=flat)](https://phpstan.org/)
[![License](https://img.shields.io/github/license/markup-carve/tempest-carve)](LICENSE)

Render [Carve](https://markup-carve.github.io/carve/), a lightweight markup
language for documents and the web, in
[Tempest](https://tempestphp.com/) views with a safe-by-default component.

**[View the live demo →](https://markup-carve.github.io/tempest-carve-demo/)**

```sh
composer require markup-carve/tempest-carve
php tempest discovery:generate --no-interaction
```

```html
<x-carve :content="$document" />
```

The renderer also supports named configurations, per-call profiles, four output
formats, diagnostics, dependency-aware includes, caching, and test assertions.
It lints, formats and imports Markdown or HTML, and ships the console commands
`carve:render`, `carve:lint`, `carve:fmt` and `carve:convert`, a `ValidCarve`
validation rule, a `CarveContent` model property type, and an optional
code highlighter.

Tempest discovers the component and its `CarveRenderer` initializer from the
package. See [Usage](docs/usage.md), [Configuration](docs/configuration.md),
[Design](docs/design.md), and [Security](docs/security.md) for details.

## Related

[tempest-highlight-carve](https://github.com/markup-carve/tempest-highlight-carve)
adds Carve as a language to [tempest/highlight](https://github.com/tempestphp/highlight),
so a `.crv` source listing can be syntax-highlighted. That is the complement to
this package: this one renders Carve to output, that one colors the source.
With both installed, the `tempest_highlight` extension highlights Carve fences
inside rendered documents.
