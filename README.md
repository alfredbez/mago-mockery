# mago-mockery

Mockery 1.6 support for the [Mago](https://github.com/carthage-software/mago) static analyzer.

## Features

- **Mock factory return types**: infers `T&Mockery\MockInterface` (and multi-interface intersections) for `Mockery::mock()`, `Mockery::spy()`, `Mockery::namedMock()`, `Mockery::instanceMock()`, `Mockery\Container::mock()`, and the global `mock()`, `spy()`, and `namedMock()` helpers.
- **Mockery target string & object specs**: resolves `alias:` and `overload:` prefixes, partial method lists (`Foo[m1, m2]`, `Foo[!m1]`), comma-separated interface lists (`Foo, Bar`), pipe-separated specs, constructor/quick-definition/closure arguments, and concrete object partial mocks (`Mockery::mock(new Foo())`).
- **Expectation chains & fluent builders**: types `shouldReceive()`, `shouldNotReceive()`, `allows(string)`, and `expects(string)` as `Mockery\CompositeExpectation` carrying the mocked type so forwarded `Expectation` methods keep real parameter validation, `getMock()` / `mock()` restore `T&MockInterface`, and `allows(array)` preserves `T&MockInterface` alongside `makePartial()`, `shouldIgnoreMissing()`, `shouldAllowMockingProtectedMethods()`, and `byDefault()`.
- **Higher-order messages**: handles zero-argument and null/empty-array forms (`shouldReceive()`, `shouldNotReceive()`, `allows()`, `allows([])`, `expects()`, `expects(null)`, `shouldHaveReceived()`, `shouldHaveReceived(null)`, `shouldNotHaveReceived()`, `shouldNotHaveReceived(null)`) and propagates the forwarded runtime return type (`CompositeExpectation<Mock>`, `VerificationDirector`, or `void`).
- **Verification return types**: returns `Mockery\VerificationDirector` for `shouldHaveReceived($method)` and `shouldHaveBeenCalled()`, and `void` for `shouldNotHaveReceived($method)` and `shouldNotHaveBeenCalled()` so invalid chains fail statically.

## Requirements

- PHP 8.1+ (test suite uses PHP 8.3+ with PHPUnit 12)
- Mago (`carthage-software/mago` 1.60.2)
- Mockery (`mockery/mockery` ^1.6)

## Installation & Worker Setup

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

## Running Tests

```bash
php tests/check-spec.php
composer test
```

`tests/run.php` validates the extension host and runs `mago analyze` twice over `tests/fixtures/` (with the extension enabled and with `--no-extensions`), asserting exact issue histograms and diagnostic messages.

`spec/catalog.json` tracks the conformance cases harvested from `phpstan/phpstan-mockery`, `psalm/mockery-psalm-plugin`, and Mockery 1.6 source contracts, verified by `tests/check-spec.php`.

## Known Gaps

- **Intersection-to-intersection parameter checks (Mago 1.60.2)**: passing `Mockery::mock(ApiClient::class)` to a parameter typed `Greeter&MockInterface` is currently accepted by Mago 1.60.2 until Mago compares intersections member by member (tracked in `tests/fixtures/Negative.php` and `MOCKERY-G001`).
- **Mocked method name validation**: literal method names passed to `shouldReceive('doesNotExist')` or higher-order `shouldReceive()->doesNotExist()` are not validated against the mocked class's method list (`MOCKERY-G002`).
- **`shouldIgnoreMissing($returnValue, $recursive)`**: Mockery's `LegacyMockInterface::shouldIgnoreMissing()` declares one parameter while `Mockery\Mock` accepts a second `$recursive` parameter.
