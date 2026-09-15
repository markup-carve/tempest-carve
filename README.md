# Carve for Tempest

Render [Carve](https://markup-carve.github.io/carve/) content in
[Tempest](https://tempestphp.com/) views with a safe-by-default component.

```sh
composer config repositories.tempest-carve vcs \
  https://github.com/markup-carve/tempest-carve.git
composer require markup-carve/tempest-carve:dev-main
php tempest discovery:generate --no-interaction
```

```html
<x-carve :content="$document" />
```

Tempest discovers the component and its `CarveRenderer` initializer from the
package. See [Usage](docs/usage.md), [Design](docs/design.md), and
[Security](docs/security.md) for details.
