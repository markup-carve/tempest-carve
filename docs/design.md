# Design

Tempest View handles layouts, components, and application data. This package handles Carve document bodies. Tempest discovers the `x-carve` component and an initializer that constructs a singleton `CarveRenderer` from `CarveConfig`.

The renderer maps configuration to Carve options for every parse, render, lint, format, import, and validation call. Its safe defaults apply to the view component. The output writer registry handles non-HTML targets. The optional Tempest cache stores completed renders under keys that include the rendering configuration. Reports bypass that cache so their diagnostics describe the current source.

The package uses the pure PHP engine from a pinned development commit on the Carve branch. It does not need Rust, FFI, or a shared library. A model property stores Carve source and renders it on demand. Passing source through the component's `content` attribute avoids interpreting it as Tempest inline slot content.
