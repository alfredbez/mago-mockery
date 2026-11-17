<?php

declare(strict_types=1);

use AlfredBez\MagoMockery\MockeryExtension;
use Mago\Sdk\Worker;

require dirname(__DIR__) . '/vendor/autoload.php';

(new Worker(MockeryExtension::create()))->run();
