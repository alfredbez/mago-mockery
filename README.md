# alfredbez/mago-mockery

Mockery 1.6 support for the [Mago](https://github.com/carthage-software/mago) static analyzer.

## Features

- **Mock factory return types.** Infers `T&Mockery\MockInterface` and multi-interface intersections for `Mockery::mock()`, `Mockery::spy()`, `Mockery::namedMock()`, `Mockery::instanceMock()`, `Mockery\Container::mock()`, and the global `mock()`, `spy()`, and `namedMock()` helpers, and narrows `Mockery::fetchMock(Foo::class)` and `Mockery\Container::fetchMock(Foo::class)` to `(Foo&Mockery\MockInterface)|null`.
- **Target string, trait, and object specs.** Resolves classes, interfaces, and traits (`Mockery::mock(SomeTrait::class)`), `alias:` and `overload:` prefixes, partial method lists (`Foo[m1, m2]`, `Foo[!m1]`), comma-separated interface lists (`Foo, Bar`), pipe-separated specs (`Foo[m1]|Bar`), constructor argument arrays, quick-definition maps, setup closures, and concrete object partial mocks (`Mockery::mock(new Foo())`).
- **Expectation and verification chains.** Types `shouldReceive()`, `shouldNotReceive()`, `allows(string)`, and `expects(string)` as `Mockery\CompositeExpectation` carrying the mocked type. Forwarded `Expectation` methods validate parameter types (including non-negative indexes for `andReturnArg()`, variadic mixed values for `andSet()`, and `array|Closure` for `VerificationDirector::withArgs()`), `getMock()` and `mock()` restore `T&MockInterface`, and `allows(array)` preserves `T&MockInterface` alongside `makePartial()`, `shouldIgnoreMissing()`, `shouldAllowMockingProtectedMethods()`, and `byDefault()`.
- **Higher-order messages.** Handles zero-argument, `null`, and empty-array forms (`shouldReceive()`, `shouldNotReceive()`, `allows()`, `allows([])`, `expects()`, `expects(null)`, `shouldHaveReceived()`, `shouldHaveReceived(null)`, `shouldNotHaveReceived()`, `shouldNotHaveReceived(null)`) and propagates the runtime return type (`CompositeExpectation<Mock>`, `VerificationDirector`, or `void`).
- **Argument matchers.** Preserves concrete `Mockery\Matcher\*` return types (including `Mockery::isEqual()`, `Mockery::isSame()`, `Mockery::anyOf()`, `Mockery::notAnyOf()`, `Mockery::not()`, and `Mockery::andAnyOtherArgs()`) and refines parameter types for `Mockery::type()`, `Mockery::pattern()`, `Mockery::ducktype()`, and `Mockery::hasKey()`.
- **PHPUnit integration.** Recognizes `startMockery()`, `setUp()`, and `mockeryTestSetUp()` as class initializers on `MockeryTestCase` subclasses and `TestCase` classes using `MockeryPHPUnitIntegration`, and registers Mockery PHPUnit lifecycle methods as entry points.
- **Opt-in strict mocked method validation (`MAGO_MOCKERY_STRICT_METHODS=1`).** Reports `alfredbez/mockery/unknown-mocked-method` when literal method names (including Demeter chain roots and associative array map keys) passed to `shouldReceive()`, `shouldNotReceive()`, `allows()`, `expects()`, `shouldHaveReceived()`, or `shouldNotHaveReceived()` do not exist on the mocked target class. Skips classes with `__call`, `__callStatic`, `@method`, or `@mixin` (including inherited parents, interfaces, and traits), `stdClass` intersections, methods explicitly whitelisted via `shouldAllowMockingMethod()` in scope, dynamic method names, and anonymous mocks.

## Supported versions

- PHP `~8.1.0 || ~8.2.0 || ~8.3.0 || ~8.4.0 || ~8.5.0 || ~8.6.0` (test suite runs on PHP 8.3 and 8.4 with PHPUnit 12)
- Mago (`carthage-software/mago`) `~1.60.2`
- Mockery (`mockery/mockery`) `^1.6`

## Installation and worker setup

`alfredbez/mago-mockery` is not published on Packagist yet. Register the GitHub repository and require `dev-main`:

```shell
composer config repositories.mago-mockery vcs https://github.com/alfredbez/mago-mockery
composer require --dev alfredbez/mago-mockery:dev-main
```

Create `.mago/mockery-worker.php`:

```php
<?php

declare(strict_types=1);

use AlfredBez\MagoMockery\MockeryExtension;
use Mago\Sdk\Worker;

require dirname(__DIR__) . '/vendor/autoload.php';

(new Worker(MockeryExtension::create()))->run();
```

Register the worker in `mago.toml` and include Mockery's library path so Mago indexes Mockery's interfaces:

```toml
[source]
includes = ["vendor/mockery/mockery/library"]

[extension-hosts.mockery]
command = ["php", ".mago/mockery-worker.php"]
workers = 1
```

Enable strict mocked-method checking by setting `MAGO_MOCKERY_STRICT_METHODS=1` in the worker environment (or passing `strictMockedMethods: true` to `MockeryPlugin`).

Verify the extension registration and run Mago:

```shell
vendor/bin/mago extension validate
vendor/bin/mago analyze
```

## Known limits (Mago 1.60.2)

- **Intersection-to-intersection parameter checks (`MOCKERY-G001`).** Passing `Mockery::mock(ApiClient::class)` to a parameter typed `Greeter&MockInterface` is still accepted by Mago 1.60.2 until Mago compares intersections member by member (pinned in `tests/fixtures/Negative.php`).
- **Direct calls to protected methods on partial mocks (`MOCKERY-G003`).** Calling a `protected` method directly on a mock after `->makePartial()->shouldAllowMockingProtectedMethods()` still triggers Mago's `invalid-method-access` (or `non-documented-method` when the class defines `__call`). Mago 1.60.2's `IssueFilterContext` does not expose receiver types, so filtering those diagnostics by source text is intentionally omitted to avoid hiding visibility bugs on non-mocks.
- **`shouldIgnoreMissing($returnValue, $recursive)`.** Mockery's `LegacyMockInterface::shouldIgnoreMissing()` declares one parameter while `Mockery\Mock` accepts a second `$recursive` parameter.

## Tests, corpus checks, and benchmarks

```shell
composer install
php tests/check-spec.php
composer test
```

`composer test` runs `php tests/run.php` (unit provider checks, extension validation, and analyzer fixture suites in default, `--no-extensions`, and `MAGO_MOCKERY_STRICT_METHODS=1` modes) and `vendor/bin/phpunit tests/runtime` (executable Mockery 1.6 runtime contract tests).

Run pinned OSS corpus snapshot checks (`mockery`, `laravel`) against `tools/corpus-baseline.json`:

```shell
php tools/corpus-run.php --check
php tools/corpus-run.php --strict-methods
php tools/corpus-run.php --update-baseline
```

Run same-host local benchmarks (`--save` / `--compare`):

```shell
php bench/run-local.php --iterations=10000 --runs=5
```

## License

MIT. See [LICENSE](LICENSE).
