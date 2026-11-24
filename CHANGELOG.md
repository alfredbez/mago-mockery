# Changelog

All notable changes to `alfredbez/mago-mockery` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.1] - 2026-11-24

### Fixed

- Strict mocked-method validation (`MAGO_MOCKERY_STRICT_METHODS=1`) tracks `shouldAllowMockingMethod()` across variable aliases, invalidates stale allowances on value and reference reassignments, and enforces dominance across branches and loops.
- Higher-order forwarding (`shouldReceive()->method()`, `expects()->method()`, `allows()->method()`, `shouldHaveReceived()->method()`, `shouldNotHaveReceived()->method()`) validates forwarded method names against the mocked target set, preserves native diagnostics for unknown methods, reports `alfredbez/mockery/unknown-mocked-method` in strict mode, and honors explicit `shouldAllowMockingMethod()` allowances.
- `Mockery::mock()` and sibling factories resolve `class-string<T>` targets into `T&Mockery\MockInterface` while keeping bare `class-string`, `string`, and `mixed` unresolved.
- `Mockery::fetchMock()` and `Container::fetchMock()` resolve same-file `Mockery::namedMock()` targets while keeping cross-file and colliding spans unresolved.
- Trait constant access filtering covers `Mockery\Container::mock()` and parenthesized factory expressions, verifies Mockery container identity, and stops suppressing after a mock variable is reassigned to a plain trait class-string.

## [0.1.0] - 2026-11-22

Version `0.1.0` targets Mago `^1.60.2` (verified on `1.60.2` and `1.61.0`) and Mockery `^1.6`.

### Added

- Production worker entrypoint `bin/mago-mockery-worker` registered under Composer `bin`, with automatic `vendor/autoload.php` discovery for both installed-dependency and local-checkout execution.
- Dist archive hygiene via `.gitattributes` (`export-ignore`) and Packagist-ready `composer.json` metadata.
- Mock factory return-type inference (`T&Mockery\MockInterface` and multi-interface intersections) for `Mockery::mock()`, `spy()`, `namedMock()`, `instanceMock()`, `Container::mock()`, `Mockery::fetchMock()`, `Container::fetchMock()`, and the global `mock()`, `spy()`, and `namedMock()` helpers.
- Support for class, interface, and trait mock targets, `alias:` and `overload:` prefixes, partial method lists, comma-separated and pipe-separated interface specs, constructor argument arrays, quick-definition maps, setup closures, and concrete object partial mocks.
- Expectation and verification chain typing for `CompositeExpectation`, forwarded `Expectation` methods, `VerificationDirector`, `getMock()` / `mock()`, fluent mock builders, and higher-order messages.
- Concrete `Mockery\Matcher\*` return types and refined parameter signatures for Mockery argument matchers (`isEqual`, `isSame`, `anyOf`, `notAnyOf`, `not`, `andAnyOtherArgs`, `andAnyOthers`, `type`, `pattern`, `ducktype`, `hasKey`, and siblings).
- PHPUnit lifecycle initializer and entry-point recognition for `MockeryTestCase` and `MockeryPHPUnitIntegration`.
- Opt-in strict mocked-method validation (`MAGO_MOCKERY_STRICT_METHODS=1`, `alfredbez/mockery/unknown-mocked-method`).
- Pinned conformance catalog (`spec/catalog.json` with 18 cases), executable Mockery 1.6 runtime contract tests, pinned OSS corpus regression harness, and CodSpeed benchmarks.

### Changed

- Documentation rewritten for first-time setup: badges, copy-paste worker configuration, check and feature tables, suppression examples, and known limitations.
