# Changelog

All notable changes to Carve for Tempest are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- Renamed the Composer package to `markup-carve/tempest-carve`

### Added

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
- Tempest integration and security tests
