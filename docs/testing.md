# Testing

Install dependencies and run the integration suite:

```sh
composer install
composer check
```

The full check runs coding standards, PHPStan at level 9, and the test suite.
The suite boots Tempest, exercises package discovery through a real view,
verifies Carve HTML output, and confirms that safe mode escapes a raw script
block. Unit coverage also exercises every output format, named profiles,
diagnostics, render-loss reporting, source-line annotations, configured
extensions, and cache-key separation.
