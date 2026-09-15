# Security

The registered renderer enables `carve-php` safe mode. It escapes raw HTML,
filters dangerous attributes and URL schemes, and applies parser safety limits.
This makes `<x-carve>` suitable for untrusted authored content under the
`carve-php` threat model.

The component emits the converter result as raw HTML because it has already
been processed by Carve. Do not replace the registered converter with an unsafe
configuration when rendering attacker-controlled source.

Applications that intentionally need trusted raw HTML should use a separately
configured `CarveConverter` in their own application code instead of the
`x-carve` component. That changes the security boundary and should be limited
to content controlled by trusted authors. See the
[`carve-php` security guide](https://github.com/markup-carve/carve-php/blob/main/docs/security.md)
before doing so.
