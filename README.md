# Carve for Tempest

[![CI](https://github.com/markup-carve/tempest-carve/actions/workflows/ci.yml/badge.svg)](https://github.com/markup-carve/tempest-carve/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-%5E8.5-777bb4)](composer.json)
[![Tempest](https://img.shields.io/badge/Tempest-%5E3.19-1a1a1a)](https://tempestphp.com/)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%209-brightgreen.svg?style=flat)](https://phpstan.org/)
[![License](https://img.shields.io/github/license/markup-carve/tempest-carve)](LICENSE)

Render [Carve](https://markup-carve.github.io/carve/) content in
[Tempest](https://tempestphp.com/) views with a safe-by-default component.

**[View the live demo →](https://markup-carve.github.io/tempest-carve-demo/)**

```sh
composer require markup-carve/tempest-carve:dev-main
php tempest discovery:generate --no-interaction
```

```html
<x-carve :content="$document" />
```

The renderer also supports named configurations, per-call profiles, four output
formats, diagnostics, dependency-aware includes, caching, and test assertions.

Tempest discovers the component and its `CarveRenderer` initializer from the
package. See [Usage](docs/usage.md), [Configuration](docs/configuration.md),
[Design](docs/design.md), and [Security](docs/security.md) for details.
