# Changelog

All notable changes to Carve for Tempest are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.0] - 2026-11-27

### Breaking

- Requires carve-php 0.2. Carve source follows the 0.2 syntax and HTML follows the 0.2 renderer; bundled fixtures use canonical 0.2 syntax (#24).
- Options use the Carve 0.2 names for profiles, heading ids, mode, smart typography, raw HTML and URL policy (#24).
- The engine classes are `MarkupCarve\Carve\Carve` and friends; the temporary `Carve2` names and the 0.1 code paths are gone (#25).

### Improvements

- Rendering, includes, lint, formatting, HTML import, validation and extensions run on carve-php 0.2 (#24).
- Markdown, text and ANSI output use the engine's built-in writers, and heading numbers, wiki links, lint and include diagnostics use the engine's APIs (#27).
- `fromMarkdown()` and `carve:convert --from=markdown` read GitHub Flavored Markdown again, so tables, task lists and strikethrough convert; `--from=gfm` is accepted too (#31).
- Named Carve 0.2 extension bundles are available through a checked factory registry; `wikilinks` takes its class and window options, `plantuml` accepts `puml`, and `softBreakMode` reaches the engine (#30).
- The render cache key includes the installed carve-php commit, so an engine update no longer serves stale HTML (#33).
- `composer.json` requires `league/commonmark`, which the Markdown import needs (#36).

## [0.1.3] - 2026-11-12

### Changed

- Require carve-php `^0.1.17`, up from `^0.1.14` (#22).
- `render()`, `renderHtml()` and `CarveContent::html()` return HTML without a
  final line feed, as the Carve HTML API specifies (#22).
- `format()` and `carve:fmt` write `|=` header cells, `***` thematic breaks and
  braced table alignment (`|={>}`); the old `|=>` spelling is still read and is
  reported by the `deprecated-cell-alignment` lint rule (#22).
- `lint()`, `carve:lint` and `ValidCarve` honor `%% carve-lint-disable-next-line`
  and the other suppression comments (#22).

### Removed

- The lint rule alias `a11y/heading-jump`; use `heading-level-skip`.
- `fromHtml()` and `carve:convert` no longer read `data-djot-*` attributes
  (markup-carve/carve#5735).

## [0.1.2] - 2026-10-23

### Added

- `CarveRenderer::lint()` and `lintIncluded()` over the engine's
  `DocumentLinter` and `IncludeLinter`, with the configured extensions (#19).
- `CarveRenderer::format()` with `alignTables`, and `fromMarkdown()` /
  `fromHtml()` import (#19).
- Console commands `carve:render`, `carve:lint`, `carve:fmt` (with
  `--align-tables`) and `carve:convert` (#19).
- `ValidCarve` validation rule (#19).
- `CarveContent` property type with a mapper caster and serializer (#19).
- Extensions configured by name or as arrays with options, including a wiki-link
  `url_template` (#19).
- `tempest_highlight` extension that highlights fenced code with
  tempest/highlight and tempest-highlight-carve (#19).

### Changed

- Require carve-php `^0.1.14`, up from `^0.1.11`, and `tempest/console`,
  `tempest/mapper` and `tempest/validation` (#19).

## [0.1.1] - 2026-10-08
### Changed

- Require carve-php `^0.1.11`, up from `^0.1.9`. That release added the
  `destination-denied` render-loss code, so a denied destination such as
  `[x](javascript:alert(1))` now appears in `RenderReport::$losses` and
  `renderWithReport(..., strictLosses: true)` rejects the document instead of
  rendering it with a blanked target. On 0.1.9 the same document reported
  nothing and strict mode accepted it.

## [0.1.0] - 2026-09-20

### Added

- First release: Carve rendering for Tempest applications
- Safe-by-default `x-carve` Tempest View component
- Discoverable singleton `CarveRenderer` service
- Tempest-native configuration with named profiles and extension registration
- Optional content-hash rendering cache through Tempest Cache
- Rendering diagnostics, profile violations, and bounded loss reports
- Opt-in source-line annotations for editor preview synchronization
- HTML, Markdown, plain-text, and ANSI service outputs
- Container-resolved include resolvers with structured results and bounded expansion
- Dependency-aware include caching through resolver-provided invalidation keys
- Named renderer configurations and per-call profile overrides
- Reusable PHPUnit assertions for rendering, safety, and warnings
