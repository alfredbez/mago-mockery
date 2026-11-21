# mago-mockery

Mockery 1.6 support for the [Mago](https://github.com/carthage-software/mago) static analyzer.

## Features

- **Mock factory return types.** Infers `T&Mockery\MockInterface` and multi-interface intersections for `Mockery::mock()`, `Mockery::spy()`, `Mockery::namedMock()`, `Mockery::instanceMock()`, `Mockery\Container::mock()`, and the global `mock()`, `spy()`, and `namedMock()` helpers.
- **Target string and object specs.** Resolves `alias:` and `overload:` prefixes, partial method lists (`Foo[m1, m2]`, `Foo[!m1]`), comma-separated interface lists (`Foo, Bar`), pipe-separated specs (`Foo[m1]|Bar`), constructor argument arrays, quick-definition maps, setup closures, and concrete object partial mocks (`Mockery::mock(new Foo())`).
- **Expectation and verification chains.** Types `shouldReceive()`, `shouldNotReceive()`, `allows(string)`, and `expects(string)` as `Mockery\CompositeExpectation` carrying the mocked type. Forwarded `Expectation` methods validate parameter types (including non-negative indexes for `andReturnArg()`, variadic mixed values for `andSet()`, and `array|Closure` for `VerificationDirector::withArgs()`), `getMock()` and `mock()` restore `T&MockInterface`, and `allows(array)` preserves `T&MockInterface` alongside `makePartial()`, `shouldIgnoreMissing()`, `shouldAllowMockingProtectedMethods()`, and `byDefault()`.
- **Higher-order messages.** Handles zero-argument, `null`, and empty-array forms (`shouldReceive()`, `shouldNotReceive()`, `allows()`, `allows([])`, `expects()`, `expects(null)`, `shouldHaveReceived()`, `shouldHaveReceived(null)`, `shouldNotHaveReceived()`, `shouldNotHaveReceived(null)`) and propagates the forwarded runtime return type (`CompositeExpectation<Mock>`, `VerificationDirector`, or `void`).
- **Argument matchers.** Preserves concrete `Mockery\Matcher\*` return types and refines parameter types for `Mockery::type()`, `Mockery::pattern()`, `Mockery::ducktype()`, and `Mockery::hasKey()`.
- **PHPUnit integration.** Recognizes `startMockery()`, `setUp()`, and `mockeryTestSetUp()` as class initializers on `MockeryTestCase` subclasses and `TestCase` classes using `MockeryPHPUnitIntegration`, and registers Mockery PHPUnit lifecycle methods as entry points.
- **Opt-in strict mocked method validation.** Set `MAGO_MOCKERY_STRICT_METHODS=1` (or pass `strictMockedMethods: true` to `MockeryPlugin`) to report `alfredbez/mockery/unknown-mocked-method` when literal method names passed to `shouldReceive()`, `shouldNotReceive()`, `allows()`, `expects()`, `shouldHaveReceived()`, or `shouldNotHaveReceived()` do not exist on the mocked target class. Classes with `__call`, `__callStatic`, `@method` declarations, or `@mixin` tags, dynamic method names, and anonymous mocks are skipped.

## Requirements

- PHP 8.1+ (the test suite runs on PHP 8.3+ with PHPUnit 12)
- Mago (`carthage-software/mago` 1.60.2)
- Mockery (`mockery/mockery` ^1.6)

## Installation and worker setup

Install the package as a dev dependency:

```bash
composer require --dev alfredbez/mago-mockery
```

Create a worker entrypoint (for example `tools/mago-mockery-worker.php`):

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
command = ["php", "tools/mago-mockery-worker.php"]
workers = 1
```

Verify the extension registration:

```bash
vendor/bin/mago extension validate
```

## Running tests

```bash
php tests/check-spec.php
composer test
```

`composer test` runs two suites:

- `composer test:analyzer` (`php tests/run.php`) runs unit provider checks, validates the extension host, analyzes `tests/fixtures/`, `tests/phpunit-fixtures/`, and `tests/strict-fixtures/` (with extension, `--no-extensions`, and `MAGO_MOCKERY_STRICT_METHODS=1`), and asserts exact diagnostic histograms and messages.
- `composer test:runtime` (`vendor/bin/phpunit tests/runtime`) runs executable Mockery 1.6 runtime contract tests against PHPUnit 12.

`spec/catalog.json` tracks conformance cases from `phpstan/phpstan-mockery`, `psalm/mockery-psalm-plugin`, and Mockery 1.6 source contracts, verified by `tests/check-spec.php`.

## Known gaps

- **Intersection-to-intersection parameter checks (Mago 1.60.2).** Passing `Mockery::mock(ApiClient::class)` to a parameter typed `Greeter&MockInterface` is still accepted by Mago 1.60.2 until Mago compares intersections member by member (pinned in `tests/fixtures/Negative.php` and `MOCKERY-G001`).
- **`shouldIgnoreMissing($returnValue, $recursive)`.** Mockery's `LegacyMockInterface::shouldIgnoreMissing()` declares one parameter while `Mockery\Mock` accepts a second `$recursive` parameter.
