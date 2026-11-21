<?php

declare(strict_types=1);

namespace MockeryBench;

use Mockery;

final class Workload2
{
    public function scenario0(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock32', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(32)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(32)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(32)->once()->andReturn('higher');
        $mock->expects()->find(32)->andReturn('expected');
        $mock->allows([])->find(32)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(32)->once();
        $spy->shouldHaveReceived()->find(32)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(32);
        $multi->flush();
        $partial->find(32);
        $spec->count();
    }

    public function scenario1(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock33', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(33)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(33)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(33)->once()->andReturn('higher');
        $mock->expects()->find(33)->andReturn('expected');
        $mock->allows([])->find(33)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(33)->once();
        $spy->shouldHaveReceived()->find(33)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(33);
        $multi->flush();
        $partial->find(33);
        $spec->count();
    }

    public function scenario2(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock34', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(34)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(34)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(34)->once()->andReturn('higher');
        $mock->expects()->find(34)->andReturn('expected');
        $mock->allows([])->find(34)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(34)->once();
        $spy->shouldHaveReceived()->find(34)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(34);
        $multi->flush();
        $partial->find(34);
        $spec->count();
    }

    public function scenario3(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock35', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(35)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(35)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(35)->once()->andReturn('higher');
        $mock->expects()->find(35)->andReturn('expected');
        $mock->allows([])->find(35)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(35)->once();
        $spy->shouldHaveReceived()->find(35)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(35);
        $multi->flush();
        $partial->find(35);
        $spec->count();
    }

    public function scenario4(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock36', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(36)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(36)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(36)->once()->andReturn('higher');
        $mock->expects()->find(36)->andReturn('expected');
        $mock->allows([])->find(36)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(36)->once();
        $spy->shouldHaveReceived()->find(36)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(36);
        $multi->flush();
        $partial->find(36);
        $spec->count();
    }

    public function scenario5(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock37', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(37)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(37)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(37)->once()->andReturn('higher');
        $mock->expects()->find(37)->andReturn('expected');
        $mock->allows([])->find(37)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(37)->once();
        $spy->shouldHaveReceived()->find(37)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(37);
        $multi->flush();
        $partial->find(37);
        $spec->count();
    }

    public function scenario6(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock38', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(38)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(38)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(38)->once()->andReturn('higher');
        $mock->expects()->find(38)->andReturn('expected');
        $mock->allows([])->find(38)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(38)->once();
        $spy->shouldHaveReceived()->find(38)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(38);
        $multi->flush();
        $partial->find(38);
        $spec->count();
    }

    public function scenario7(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock39', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(39)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(39)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(39)->once()->andReturn('higher');
        $mock->expects()->find(39)->andReturn('expected');
        $mock->allows([])->find(39)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(39)->once();
        $spy->shouldHaveReceived()->find(39)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(39);
        $multi->flush();
        $partial->find(39);
        $spec->count();
    }

    public function scenario8(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock40', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(40)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(40)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(40)->once()->andReturn('higher');
        $mock->expects()->find(40)->andReturn('expected');
        $mock->allows([])->find(40)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(40)->once();
        $spy->shouldHaveReceived()->find(40)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(40);
        $multi->flush();
        $partial->find(40);
        $spec->count();
    }

    public function scenario9(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock41', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(41)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(41)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(41)->once()->andReturn('higher');
        $mock->expects()->find(41)->andReturn('expected');
        $mock->allows([])->find(41)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(41)->once();
        $spy->shouldHaveReceived()->find(41)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(41);
        $multi->flush();
        $partial->find(41);
        $spec->count();
    }

    public function scenario10(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock42', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(42)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(42)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(42)->once()->andReturn('higher');
        $mock->expects()->find(42)->andReturn('expected');
        $mock->allows([])->find(42)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(42)->once();
        $spy->shouldHaveReceived()->find(42)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(42);
        $multi->flush();
        $partial->find(42);
        $spec->count();
    }

    public function scenario11(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock43', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(43)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(43)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(43)->once()->andReturn('higher');
        $mock->expects()->find(43)->andReturn('expected');
        $mock->allows([])->find(43)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(43)->once();
        $spy->shouldHaveReceived()->find(43)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(43);
        $multi->flush();
        $partial->find(43);
        $spec->count();
    }

    public function scenario12(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock44', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(44)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(44)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(44)->once()->andReturn('higher');
        $mock->expects()->find(44)->andReturn('expected');
        $mock->allows([])->find(44)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(44)->once();
        $spy->shouldHaveReceived()->find(44)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(44);
        $multi->flush();
        $partial->find(44);
        $spec->count();
    }

    public function scenario13(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock45', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(45)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(45)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(45)->once()->andReturn('higher');
        $mock->expects()->find(45)->andReturn('expected');
        $mock->allows([])->find(45)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(45)->once();
        $spy->shouldHaveReceived()->find(45)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(45);
        $multi->flush();
        $partial->find(45);
        $spec->count();
    }

    public function scenario14(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock46', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(46)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(46)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(46)->once()->andReturn('higher');
        $mock->expects()->find(46)->andReturn('expected');
        $mock->allows([])->find(46)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(46)->once();
        $spy->shouldHaveReceived()->find(46)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(46);
        $multi->flush();
        $partial->find(46);
        $spec->count();
    }

    public function scenario15(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock47', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(47)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(47)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(47)->once()->andReturn('higher');
        $mock->expects()->find(47)->andReturn('expected');
        $mock->allows([])->find(47)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(47)->once();
        $spy->shouldHaveReceived()->find(47)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(47);
        $multi->flush();
        $partial->find(47);
        $spec->count();
    }
}
