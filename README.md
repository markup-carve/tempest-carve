# Carve for Tempest

Render [Carve](https://markup-carve.github.io/carve/) content in
[Tempest](https://tempestphp.com/) views with a safe-by-default component.

```sh
composer require markup-carve/tempest-carve:dev-main
php tempest discovery:generate --no-interaction
```

```html
<x-carve :content="$document" />
```

Tempest discovers the component and its `CarveRenderer` initializer from the
package. See [Usage](docs/usage.md), [Configuration](docs/configuration.md),
[Design](docs/design.md), and [Security](docs/security.md) for details.
