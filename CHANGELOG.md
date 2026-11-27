# Changelog

All notable changes to `alfredbez/mago-mockery` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.2] - 2026-11-27

### Fixed

- Added `CallableSignatureOverride` support for `Mockery::mock()`, `Mockery::spy()`, `Mockery::namedMock()`, `Mockery::instanceMock()`, `Container::mock()`, global `mock()`, `spy()`, and `namedMock()`, and `LegacyMockInterface::shouldReceive()` / `shouldNotReceive()` so valid expectation maps, constructor argument arrays, and `namedMock()` alias strings pass on Mockery 1.6.12 through 1.6.15 without external stubs while invalid shapes remain rejected.
- `MockTypeFactory::fromInvocation()` preserves template parameter `T` intersected with `MockInterface` for generic `class-string<T>` arguments.
- `ExpectationAndMatcherSignatureProvider::getReturnType()` preserves `T&MockInterface` across `makePartial()`, `byDefault()`, `shouldAllowMockingProtectedMethods()`, `shouldAllowMockingMethod()`, and `shouldDeferMissing()` on Mockery 1.6.12 docblocks.
- `CompositeExpectationProvider` classifies array emptiness across unions containing `array{}` (such as `array{}|array{greet: string}` and `array{}|non-empty-array<string, string>`), preserving `HigherOrderMessage|(T&MockInterface)`.
- `TraitMockExpressionEvaluator` preserves `static` local mock provenance across known non-reentrant Mockery calls without partial-mock constructor specs, and `TraitMockFlowAnalyzer::hasLoopControlJump()` tracks nested `switch` and loop depths with numeric `break N` / `continue N` levels inside `do ... while (false)`.
- `NamedMockIndex` walks out of closures and arrow functions, indexes trait lifecycle hooks (respecting class overrides and `insteadof`), sorts scope entries in source order, enforces dominance (including nullsafe and `??=` short-circuits) before shadowing lifecycle aliases, and avoids PHP 8.2+ enum constant expressions for PHP 8.1 compatibility.
- `StrictAllowanceResolver` invalidates `$this->*` allowances on user helper calls and `$self = $this` aliases while exempting `PHPUnit\Framework\*` and `Mockery\*` calls, restricts chain stripping to fluent methods, and tracks by-reference calls and unpacked reference arguments; `StrictMockedMethodHook` checks every member of finite literal-string unions with case-insensitive deduplication.
- Documented `-d memory_limit=512M` worker headroom for large codebases in `README.md`.

## [1.0.1] - 2026-11-26

### Changed

- CodSpeed CI benchmarks run in deterministic `simulation` mode with `simulation-track-subprocess: "true"` instead of `walltime` on mixed `ubuntu-latest` CPUs, eliminating false hardware-drift failures and enabling flamegraphs.

### Fixed

- `MockTypeFactory` reuses the plain-class type cache for non-generic `NamedObjectType` targets (object partial mocks and plain `class-string<T>` arguments) and normalizes empty parameter and variance lists to `null`, restoring `factory_partial` performance while keeping generic and remapped targets uncached to prevent collisions.

## [1.0.0] - 2026-11-26

### Added

- Compatibility and SemVer policy in the README covering the public PHP API, worker binary, diagnostic codes, default-on versus opt-in checks, suppression, and supported PHP, Mago, and Mockery floors.
- Floor-compatible CI lanes for PHP 8.1 and 8.2, full analyzer and runtime suites on PHP 8.3 through 8.5, and pinned Mago `1.60.2`, `1.61.0`, and `latest` jobs, including bounded self-analysis of `src/` and `bin/`.
- `[extension-hosts.mockery] environment` documentation for `MAGO_MOCKERY_STRICT_METHODS`.

### Fixed

- Mock factory return types keep generic parameters from object partials (`Mockery::mock(new GenericBox('hello'))`) and `class-string<T>` arguments (`class-string<GenericBox<string>>`).
- Mockery 1.6 signature mismatches: `shouldIgnoreMissing(null, true)`, `asUndefined()`, `Expectation::set()` variadic values, `allows(null)` higher-order form, possibly-empty `allows($map)` return type `HigherOrderMessage|(T&MockInterface)`, and `fetchMock(string)` versus Mockery's `class-string` docblock.
- Higher-order `shouldNotReceive()`, `allows([])`, `expects(null)`, `shouldHaveReceived(null)`, and `shouldNotHaveReceived(null)` honor `shouldAllowMockingMethod()`, including allowances on `$this->property`.
- Strict mode reports exactly one `alfredbez/mockery/unknown-mocked-method` diagnostic for an unknown higher-order call instead of cascading `non-documented-method`.
- `Mockery::fetchMock()` resolves `namedMock()` aliases from the same method or from the enclosing class `setUp()`, `startMockery()`, `mockeryTestSetUp()`, and `#[Before]` hooks, and no longer leaks aliases from sibling test methods or other classes in the same file.

## [0.1.3] - 2026-11-25

### Fixed

- Trait constant access filtering separates `static` local variables from reference-escaped cells so pure built-in literal calls preserve current static-local mock provenance, while calls that may re-enter, first-call-only static initializers, magic methods, destructors, and reference escapes still invalidate stale state.
- Trait constant access filtering models guaranteed first iterations for `do ... while` loops with statically falsy conditions without dropping later-iteration or post-reassignment diagnostics, and retains mock provenance through `shouldDeferMissing()` (also mirrored in strict allowance resolution), `clone` expressions (including PHP 8.5 named-argument `clone(...)`), and null-coalescing `??=` / `??` refinement from known-null and mock-or-null states.

## [0.1.2] - 2026-11-25

### Fixed

- Trait constant access filtering enforces intra-procedural path dominance so conditional mock assignments with a reachable non-mock path retain Mago's `direct-trait-constant-access` diagnostic, while dominated mocks across straight-line code, both branches, and post-conditional reassignments stay clean.
- Trait constant access filtering tracks by-reference aliases (`$alias = &$mock`), reference-group invalidation, `unset()` detachment, reference cycles, escaped references, and by-reference call arguments so valid mock aliases no longer emit `direct-trait-constant-access` while non-mock and invalidated aliases keep the diagnostic.

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
