# Contributing

Install dependencies and run the full check before opening a pull request:

```sh
composer install
composer check
```

`composer check` runs the coding-standard check, PHPStan at level 9, and the
integration test suite. Use `composer cs-fix` to apply safe formatting fixes.

Changes to the component or initializer should include an integration test.
Security-sensitive changes must retain safe defaults for untrusted Carve input.
