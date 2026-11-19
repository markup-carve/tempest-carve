# Security

The component renders through Carve with `rawHtml: false` and the engine's default URL policy. Raw HTML stays disabled unless an application explicitly enables it for trusted content. The `profile` option further limits what is published. The view component emits the engine's HTML as raw output after rendering.

A custom include resolver is an application trust boundary. It must enforce authorization and path containment for its source. The engine enforces its include depth and byte limits, and `IncludeOptions` may narrow them. Include caching is off unless the resolver supplies an `IncludeCacheKeyProvider` key that changes when reachable content changes.

A registered output writer or extension renderer runs trusted application code. Review one before using it with untrusted source. A Markdown, text, or ANSI writer is unavailable until explicitly registered; the adapter does not run the 0.1 writer behind a 0.2 parse.
