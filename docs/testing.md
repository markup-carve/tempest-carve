# Testing

Install the pinned Carve dependency and run the package checks:

```sh
composer update
composer check
```

The test suite boots Tempest, renders the component, and checks the 0.2 HTML, diagnostics, includes, formatting, import, validation, cache, and writer registration paths. Extension tests cover named bundles and document transforms.

Composer validation on this branch reports its expected warning for the development commit pin. CI runs schema validation without `--strict` so the pin remains at the exact reviewed engine commit.
