<?php

declare(strict_types=1);

namespace {
    use Mockery\Container;
    use Mockery\LegacyMockInterface;
    use Mockery\MockInterface;

    /**
     * @template TMock of object
     * @param class-string<TMock>|TMock|array<TMock> ...$args
     * @return LegacyMockInterface&MockInterface&TMock
     */
    function mock(...$args)
    {
        return Mockery::mock(...$args);
    }

    /**
     * @template TMock of object
     * @param class-string<TMock>|TMock|array<TMock> ...$args
     * @return LegacyMockInterface&MockInterface&TMock
     */
    function spy(...$args)
    {
        return Mockery::spy(...$args);
    }

    /**
     * @template TMock of object
     * @param class-string<TMock>|TMock|array<TMock> ...$args
     * @return LegacyMockInterface&MockInterface&TMock
     */
    function namedMock(...$args)
    {
        return Mockery::namedMock(...$args);
    }

    class Mockery
    {
        /**
         * @template TMock of object
         * @param class-string<TMock>|TMock|array<TMock> ...$args
         * @return LegacyMockInterface&MockInterface&TMock
         */
        public static function mock(...$args)
        {
            throw new \RuntimeException('stub');
        }

        /**
         * @template TMock of object
         * @param class-string<TMock>|TMock|array<TMock> ...$args
         * @return LegacyMockInterface&MockInterface&TMock
         */
        public static function spy(...$args)
        {
            throw new \RuntimeException('stub');
        }

        /**
         * @template TMock of object
         * @param class-string<TMock>|TMock|array<TMock> ...$args
         * @return LegacyMockInterface&MockInterface&TMock
         */
        public static function namedMock(...$args)
        {
            throw new \RuntimeException('stub');
        }

        /**
         * @template TMock of object
         * @param class-string<TMock>|TMock|array<TMock> ...$args
         * @return LegacyMockInterface&MockInterface&TMock
         */
        public static function instanceMock(...$args)
        {
            throw new \RuntimeException('stub');
        }

        /**
         * @return Container
         */
        public static function getContainer()
        {
            throw new \RuntimeException('stub');
        }

        /**
         * @param class-string $name
         * @return null|MockInterface
         */
        public static function fetchMock($name)
        {
            return null;
        }
    }
}

namespace Mockery {
    use Closure;

    class Container
    {
        /**
         * @template TMock of object
         * @param class-string<TMock>|TMock|array<TMock> ...$args
         * @return LegacyMockInterface&MockInterface&TMock
         */
        public function mock(...$args)
        {
            throw new \RuntimeException('stub');
        }

        /**
         * @param class-string $reference
         * @return null|MockInterface
         */
        public function fetchMock($reference)
        {
            return null;
        }
    }

    interface ExpectationInterface
    {
        /**
         * @param mixed ...$args
         * @return self
         */
        public function andReturn(...$args);

        /**
         * @return MockInterface
         */
        public function getMock();
    }

    class Expectation implements ExpectationInterface
    {
        /**
         * @param mixed ...$args
         * @return self
         */
        public function andReturn(...$args)
        {
            return $this;
        }

        /**
         * @return self
         */
        public function once()
        {
            return $this;
        }

        /**
         * @param mixed ...$args
         * @return self
         */
        public function with(...$args)
        {
            return $this;
        }

        /**
         * @return MockInterface
         */
        public function getMock()
        {
            throw new \RuntimeException('stub');
        }
    }

    class CompositeExpectation implements ExpectationInterface
    {
        /**
         * @param string $method
         * @param array<mixed> $args
         * @return self
         */
        public function __call($method, array $args)
        {
            return $this;
        }

        /**
         * @param mixed ...$args
         * @return self
         */
        public function andReturn(...$args)
        {
            return $this;
        }

        /**
         * @return MockInterface
         */
        public function getMock()
        {
            throw new \RuntimeException('stub');
        }

        /**
         * @return MockInterface
         */
        public function mock()
        {
            throw new \RuntimeException('stub');
        }

        /**
         * @param mixed ...$args
         * @return Expectation|ExpectationInterface|HigherOrderMessage
         */
        public function shouldReceive(...$args)
        {
            throw new \RuntimeException('stub');
        }

        /**
         * @param mixed ...$args
         * @return Expectation|ExpectationInterface|HigherOrderMessage
         */
        public function shouldNotReceive(...$args)
        {
            throw new \RuntimeException('stub');
        }
    }

    class HigherOrderMessage
    {
        /**
         * @param string $method
         * @param array<mixed> $args
         * @return Expectation|ExpectationInterface
         */
        public function __call($method, $args)
        {
            throw new \RuntimeException('stub');
        }
    }

    class ExpectsHigherOrderMessage extends HigherOrderMessage
    {
    }

    interface LegacyMockInterface
    {
        /**
         * @return self
         */
        public function byDefault();

        /**
         * @return self
         */
        public function makePartial();

        /**
         * @param string $method
         * @return self
         */
        public function shouldAllowMockingMethod($method);

        /**
         * @return self
         */
        public function shouldAllowMockingProtectedMethods();

        /**
         * @return self
         */
        public function shouldDeferMissing();

        /**
         * @param mixed $returnValue
         * @return self
         */
        public function shouldIgnoreMissing($returnValue = null);

        /**
         * @param string ...$methodNames
         * @return Expectation|ExpectationInterface|HigherOrderMessage
         */
        public function shouldNotReceive(...$methodNames);

        /**
         * @param string ...$methodNames
         * @return Expectation|ExpectationInterface|HigherOrderMessage
         */
        public function shouldReceive(...$methodNames);
    }

    interface MockInterface extends LegacyMockInterface
    {
        /**
         * @param mixed $something
         * @return Expectation|ExpectationInterface|HigherOrderMessage
         */
        public function allows($something = []);

        /**
         * @param mixed $something
         * @return Expectation|ExpectationInterface|HigherOrderMessage
         */
        public function expects($something = null);
    }

    class Mock implements MockInterface
    {
        /**
         * @return self
         */
        public function asUndefined()
        {
            return $this;
        }

        /**
         * @return self
         */
        public function byDefault()
        {
            return $this;
        }

        /**
         * @return self
         */
        public function makePartial()
        {
            return $this;
        }

        /**
         * @param string $method
         * @return self
         */
        public function shouldAllowMockingMethod($method)
        {
            return $this;
        }

        /**
         * @return self
         */
        public function shouldAllowMockingProtectedMethods()
        {
            return $this;
        }

        /**
         * @return self
         */
        public function shouldDeferMissing()
        {
            return $this;
        }

        /**
         * @param mixed $returnValue
         * @return self
         */
        public function shouldIgnoreMissing($returnValue = null)
        {
            return $this;
        }

        /**
         * @param string ...$methodNames
         * @return Expectation|ExpectationInterface|HigherOrderMessage
         */
        public function shouldNotReceive(...$methodNames)
        {
            throw new \RuntimeException('stub');
        }

        /**
         * @param string ...$methodNames
         * @return Expectation|ExpectationInterface|HigherOrderMessage
         */
        public function shouldReceive(...$methodNames)
        {
            throw new \RuntimeException('stub');
        }

        /**
         * @param mixed $something
         * @return Expectation|ExpectationInterface|HigherOrderMessage
         */
        public function allows($something = [])
        {
            throw new \RuntimeException('stub');
        }

        /**
         * @param mixed $something
         * @return Expectation|ExpectationInterface|HigherOrderMessage
         */
        public function expects($something = null)
        {
            throw new \RuntimeException('stub');
        }
    }
}
