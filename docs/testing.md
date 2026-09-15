# Testing

Install dependencies and run the integration suite:

```sh
composer install
composer test
```

The suite boots Tempest, exercises package discovery through a real view,
verifies Carve HTML output, and confirms that safe mode escapes a raw script
block.
