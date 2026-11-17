<?php

declare(strict_types=1);

namespace AlfredBez\MagoMockery;

use AlfredBez\MagoMockery\Analyzer\MockeryPlugin;
use Mago\Sdk\Extension;

final class MockeryExtension
{
    public const VERSION = '0.1.0';

    private function __construct() {}

    public static function create(): Extension
    {
        return new Extension(
            identifier: 'alfredbez/mago-mockery',
            name: 'Mockery',
            version: self::VERSION,
            analyzerPlugins: [new MockeryPlugin()],
        );
    }
}
