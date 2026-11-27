# alfredbez/mago-mockery

[![ci](https://github.com/alfredbez/mago-mockery/actions/workflows/ci.yml/badge.svg)](https://github.com/alfredbez/mago-mockery/actions/workflows/ci.yml)
[![Packagist version](https://img.shields.io/packagist/v/alfredbez/mago-mockery.svg)](https://packagist.org/packages/alfredbez/mago-mockery)
[![Packagist downloads](https://img.shields.io/packagist/dt/alfredbez/mago-mockery.svg)](https://packagist.org/packages/alfredbez/mago-mockery)
![PHP](https://img.shields.io/badge/php-8.1%20to%208.6-777BB4)
[![Mago](https://img.shields.io/badge/mago-%5E1.60.2-blue)](https://github.com/carthage-software/mago)
[![Mockery](https://img.shields.io/badge/mockery-%5E1.6-red)](https://github.com/mockery/mockery)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Mockery 1.6 support for [Mago](https://github.com/carthage-software/mago). It types mocks as the mocked class plus `Mockery\MockInterface`, so calls on a mock are checked like calls on the real object.

- Types mock factories as the mocked class intersected with `MockInterface`.
- Keeps `shouldReceive()`, `expects()`, `andReturn()`, `with()`, and the rest of the chain typed.
- Handles trait mocks, `alias:` and `overload:` specs, partial mocks, and multiple interfaces.
- Offers an opt-in check for literal method names that the mocked class does not have.

A wrong mock type Mago reports before PHPUnit runs:

```php
$api = Mockery::mock(Greeter::class);
$api->shouldReceive('greet')->andReturn('hello');
$this->acceptApiClient($api); // invalid-argument: a Greeter mock is not an ApiClient
```

## Setup

Requires PHP 8.1 or newer, Mago `^1.60.2`, and Mockery `^1.6`.

```shell
composer require --dev alfredbez/mago-mockery
```

Register the bundled worker in `mago.toml`:

```toml
[source]
includes = ["vendor/mockery/mockery/library"]

[extension-hosts.mockery]
command = ["php", "-d", "xdebug.mode=off", "-d", "pcov.enabled=0", "-d", "memory_limit=512M", "vendor/bin/mago-mockery-worker"]
workers = 1
# Optional. Default is off. Set to "1" to report unknown mocked method names.
# environment = { MAGO_MOCKERY_STRICT_METHODS = "1" }
```

- `[source] includes` must point at Mockery's library directory. Mago reads Mockery's interfaces and matcher classes from there. Without it, mock return types have no base to intersect with.
- The `-d` flags keep Xdebug and PCOV out of the worker and give the worker 512M of headroom for multi-thousand-line test files. Small and medium suites also run within PHP's default 128M limit.
- Pass worker environment variables through `[extension-hosts.mockery] environment`. `MAGO_MOCKERY_STRICT_METHODS=1` turns on the opt-in unknown-method check. Leave the table unset, or omit the key, to keep the default off.

Check the worker and run the analyzer:

```shell
vendor/bin/mago extension validate
vendor/bin/mago analyze
```

## Checks

| Code | Level | What it reports |
| --- | --- | --- |
| `alfredbez/mockery/unknown-mocked-method` | error | A literal or higher-order method name passed to `shouldReceive()`, `shouldNotReceive()`, `allows()`, `expects()`, `shouldHaveReceived()`, or `shouldNotHaveReceived()` does not exist on the mocked class. Opt-in with `MAGO_MOCKERY_STRICT_METHODS=1`. |

The check also reads Demeter chain roots (`'user->name'`) and associative array map keys passed to `allows()` or `expects()`. It skips:

- classes with `__call`, `__callStatic`, `@method`, or `@mixin`, including inherited ones
- `stdClass` intersections
- methods allowed through `shouldAllowMockingMethod()` in the same scope
- dynamic method names and anonymous mocks

To silence the check in one project:

```toml
[analyzer]
ignore = ["alfredbez/mockery/unknown-mocked-method"]
```

To silence one call:

```php
// @mago-ignore analysis:alfredbez/mockery/unknown-mocked-method
$mock->shouldReceive('handledByMagicCall');
```

## What it types

| Feature | What Mago knows |
| --- | --- |
| `Mockery::mock()`, `spy()`, `namedMock()`, `instanceMock()`, `Container::mock()` | `T&MockInterface` for literal targets, `class-string<T>` (including generic `class-string<Box<T>>`), and object partials that keep `T`'s type parameters, or an intersection of every mocked interface. |
| Global `mock()`, `spy()`, and `namedMock()` helpers | The same return types as the static factories. |
| `Mockery::fetchMock()` and `Container::fetchMock()` | `T&MockInterface`, or `null` when no mock is registered, including same-file `namedMock()` aliases and `class-string<T>`. |
| Spec strings | Class, interface, and trait targets, `alias:` and `overload:` prefixes, partial lists (`Foo[m1, m2]`, `Foo[!m1]`), comma and pipe separated interfaces, constructor argument arrays, quick definition maps, setup closures, and object partials. |
| `shouldReceive()`, `shouldNotReceive()`, `allows()`, `expects()` | `CompositeExpectation` carrying the mocked type. |
| Forwarded `Expectation` methods | Parameter types, including non-negative indexes for `andReturnArg()`, variadic mixed values for `andSet()`, and `array` or `Closure` for `VerificationDirector::withArgs()`. |
| `getMock()` and `mock()` on an expectation | `T&MockInterface`. |
| Fluent builders | `makePartial()`, `shouldIgnoreMissing($returnValue, $recursive)`, `asUndefined()`, `shouldAllowMockingProtectedMethods()`, and `byDefault()` keep `T&MockInterface`. |
| Higher-order messages | Zero-argument, `null`, and empty-array forms of `shouldReceive()`, `shouldNotReceive()`, `allows()`, `expects()`, `shouldHaveReceived()`, and `shouldNotHaveReceived()`, with the runtime return type (`CompositeExpectation`, `VerificationDirector`, or `void`). |
| Argument matchers | Concrete `Mockery\Matcher\*` types, plus refined parameter types for `Mockery::type()`, `pattern()`, `ducktype()`, and `hasKey()`. |
| PHPUnit integration | `startMockery()`, `setUp()`, and `mockeryTestSetUp()` as class initializers on `MockeryTestCase` subclasses and `TestCase` classes using `MockeryPHPUnitIntegration`. Mockery lifecycle methods are entry points. |

## Compatibility and SemVer policy

This project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html) from `1.0.0`.

**Public PHP API.** `AlfredBez\MagoMockery\MockeryExtension::create()` is the supported entry point. Analyzer plugins, providers, hooks, and helper classes under `AlfredBez\MagoMockery\Analyzer` are `@internal`. They can change without a major version bump.

**Worker.** `bin/mago-mockery-worker` (Composer `vendor/bin/mago-mockery-worker`) is the supported CLI. Stdout is the Mago extension protocol. Diagnostics and warnings go to stderr.

**Diagnostic codes.** `alfredbez/mockery/unknown-mocked-method` is the only extension-owned analyzer code today. It is **opt-in**. The default worker does not emit it unless `MAGO_MOCKERY_STRICT_METHODS` is set to `1`, `true`, `yes`, or `on`. Adding a new default-on diagnostic is a minor bump. Changing the meaning of an existing code, removing a code, or turning an opt-in check on by default is a major bump.

**Suppression.** Project-wide `[analyzer] ignore = ["alfredbez/mockery/unknown-mocked-method"]` and per-call `// @mago-ignore analysis:alfredbez/mockery/unknown-mocked-method` remain supported.

**Supported floors.** PHP `~8.1` through `~8.6`, Mago `^1.60.2` (verified on `1.60.2` and `1.61.0`), Mockery `^1.6`.

## Known limitations

- Passing `Mockery::mock(ApiClient::class)` to a parameter typed `Greeter&MockInterface` is accepted by Mago `1.60.2` through `1.61.0`. Mago does not compare intersections member by member yet. The case is pinned in `tests/fixtures/Negative.php`.
- Calling a `protected` method directly on a mock after `->makePartial()->shouldAllowMockingProtectedMethods()` reports Mago's own `invalid-method-access`, or `non-documented-method` when the class defines `__call`. Mago `1.60.2` through `1.61.0` does not expose receiver types to `IssueFilterContext`, so the extension does not filter those diagnostics. Filtering by source text would also hide real visibility bugs on non-mocks.
- Literal and higher-order method names are only checked when `MAGO_MOCKERY_STRICT_METHODS=1` is set. Without it, `shouldReceive('doesNotExist')` and `shouldReceive()->doesNotExist()` stay silent. With it, each unknown higher-order call reports exactly one `alfredbez/mockery/unknown-mocked-method` diagnostic.
- In strict mode, the analyzer reports an unknown mocked method unless a `shouldAllowMockingMethod()` call on the same mock identity (a local variable or a straight-line `$this->property`) dominates the expectation without a reassignment or unmerged branch split.

## Development

```shell
composer install
php tests/check-spec.php
composer test
```

`composer test` runs the analyzer suite (`php tests/run.php`) and the Mockery runtime contract tests (`vendor/bin/phpunit tests/runtime`). The analyzer suite validates `bin/mago-mockery-worker`, provider unit checks, and fixture suites in default, `--no-extensions`, and `MAGO_MOCKERY_STRICT_METHODS=1` modes.

Run pinned OSS corpus checks (`mockery`, `laravel`):

```shell
php tools/corpus-run.php --check
php tools/corpus-run.php --strict-methods
php tools/corpus-run.php --update-baseline
```

Run local benchmarks and verify conformance hashes:

```shell
php bench/run-local.php --iterations=10000 --runs=5
php spec/harvest.php
```

## License

MIT. See [LICENSE](LICENSE).
