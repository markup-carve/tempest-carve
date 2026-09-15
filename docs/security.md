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

Profiles restrict which Carve features may be published but do not replace safe
mode. Cache keys include the source and rendering configuration; use a dedicated
cache store if application policy requires Carve output to be isolated from
other cached data.

Include resolvers are application trust boundaries. Configure a resolver only
for content the application is prepared to publish, enforce authorization and
path containment inside it, and keep safe rendering enabled when included
content is less trusted. Expansion limits are configurable through
`IncludeOptions`. Include caching is disabled unless the resolver supplies an
explicit invalidation key through `IncludeCacheKeyProvider`.
Include warning `detail` values may contain resolver exception messages and
must not be displayed to untrusted users without application-level filtering.
