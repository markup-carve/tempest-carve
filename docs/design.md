# Design

Carve complements Tempest View rather than replacing it. Tempest View remains
responsible for layouts, components, application data, and HTML composition;
Carve renders document bodies authored in a lightweight markup language.

The package follows the shape of Tempest's built-in `x-markdown` support:

1. Tempest discovers the vendor-provided `x-carve.view.php` component.
2. It discovers an initializer that registers a singleton `CarveRenderer`.
3. The component resolves that converter and emits the rendered HTML.

Depending on `tempest/view` makes the package discoverable without application
service-provider code. The integration uses the native PHP Carve implementation
to preserve ordinary Composer deployment; it does not require Rust, FFI, or a
shared library.

The component deliberately accepts source through its `content` attribute.
Inline slot content is first interpreted by Tempest View and is therefore not a
reliable transport for arbitrary Carve source containing template expressions.

`CarveRenderer` owns the `carve-php` converter instead of registering that
third-party class directly in Tempest's container. This keeps the integration's
safe defaults behind a stable, narrow API and avoids claiming a shared binding
that another package or application may also want to configure.
