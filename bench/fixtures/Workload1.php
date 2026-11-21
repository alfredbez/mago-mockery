<?php

declare(strict_types=1);

namespace MockeryBench;

use Mockery;

final class Workload1
{
    public function scenario0(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock16', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(16)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(16)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(16)->once()->andReturn('higher');
        $mock->expects()->find(16)->andReturn('expected');
        $mock->allows([])->find(16)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(16)->once();
        $spy->shouldHaveReceived()->find(16)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(16);
        $multi->flush();
        $partial->find(16);
        $spec->count();
    }

    public function scenario1(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock17', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(17)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(17)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(17)->once()->andReturn('higher');
        $mock->expects()->find(17)->andReturn('expected');
        $mock->allows([])->find(17)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(17)->once();
        $spy->shouldHaveReceived()->find(17)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(17);
        $multi->flush();
        $partial->find(17);
        $spec->count();
    }

    public function scenario2(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock18', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(18)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(18)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(18)->once()->andReturn('higher');
        $mock->expects()->find(18)->andReturn('expected');
        $mock->allows([])->find(18)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(18)->once();
        $spy->shouldHaveReceived()->find(18)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(18);
        $multi->flush();
        $partial->find(18);
        $spec->count();
    }

    public function scenario3(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock19', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(19)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(19)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(19)->once()->andReturn('higher');
        $mock->expects()->find(19)->andReturn('expected');
        $mock->allows([])->find(19)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(19)->once();
        $spy->shouldHaveReceived()->find(19)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(19);
        $multi->flush();
        $partial->find(19);
        $spec->count();
    }

    public function scenario4(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock20', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(20)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(20)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(20)->once()->andReturn('higher');
        $mock->expects()->find(20)->andReturn('expected');
        $mock->allows([])->find(20)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(20)->once();
        $spy->shouldHaveReceived()->find(20)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(20);
        $multi->flush();
        $partial->find(20);
        $spec->count();
    }

    public function scenario5(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock21', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(21)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(21)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(21)->once()->andReturn('higher');
        $mock->expects()->find(21)->andReturn('expected');
        $mock->allows([])->find(21)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(21)->once();
        $spy->shouldHaveReceived()->find(21)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(21);
        $multi->flush();
        $partial->find(21);
        $spec->count();
    }

    public function scenario6(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock22', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(22)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(22)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(22)->once()->andReturn('higher');
        $mock->expects()->find(22)->andReturn('expected');
        $mock->allows([])->find(22)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(22)->once();
        $spy->shouldHaveReceived()->find(22)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(22);
        $multi->flush();
        $partial->find(22);
        $spec->count();
    }

    public function scenario7(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock23', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(23)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(23)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(23)->once()->andReturn('higher');
        $mock->expects()->find(23)->andReturn('expected');
        $mock->allows([])->find(23)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(23)->once();
        $spy->shouldHaveReceived()->find(23)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(23);
        $multi->flush();
        $partial->find(23);
        $spec->count();
    }

    public function scenario8(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock24', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(24)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(24)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(24)->once()->andReturn('higher');
        $mock->expects()->find(24)->andReturn('expected');
        $mock->allows([])->find(24)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(24)->once();
        $spy->shouldHaveReceived()->find(24)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(24);
        $multi->flush();
        $partial->find(24);
        $spec->count();
    }

    public function scenario9(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock25', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(25)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(25)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(25)->once()->andReturn('higher');
        $mock->expects()->find(25)->andReturn('expected');
        $mock->allows([])->find(25)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(25)->once();
        $spy->shouldHaveReceived()->find(25)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(25);
        $multi->flush();
        $partial->find(25);
        $spec->count();
    }

    public function scenario10(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock26', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(26)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(26)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(26)->once()->andReturn('higher');
        $mock->expects()->find(26)->andReturn('expected');
        $mock->allows([])->find(26)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(26)->once();
        $spy->shouldHaveReceived()->find(26)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(26);
        $multi->flush();
        $partial->find(26);
        $spec->count();
    }

    public function scenario11(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock27', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(27)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(27)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(27)->once()->andReturn('higher');
        $mock->expects()->find(27)->andReturn('expected');
        $mock->allows([])->find(27)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(27)->once();
        $spy->shouldHaveReceived()->find(27)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(27);
        $multi->flush();
        $partial->find(27);
        $spec->count();
    }

    public function scenario12(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock28', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(28)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(28)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(28)->once()->andReturn('higher');
        $mock->expects()->find(28)->andReturn('expected');
        $mock->allows([])->find(28)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(28)->once();
        $spy->shouldHaveReceived()->find(28)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(28);
        $multi->flush();
        $partial->find(28);
        $spec->count();
    }

    public function scenario13(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock29', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(29)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(29)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(29)->once()->andReturn('higher');
        $mock->expects()->find(29)->andReturn('expected');
        $mock->allows([])->find(29)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(29)->once();
        $spy->shouldHaveReceived()->find(29)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(29);
        $multi->flush();
        $partial->find(29);
        $spec->count();
    }

    public function scenario14(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock30', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(30)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(30)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(30)->once()->andReturn('higher');
        $mock->expects()->find(30)->andReturn('expected');
        $mock->allows([])->find(30)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(30)->once();
        $spy->shouldHaveReceived()->find(30)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(30);
        $multi->flush();
        $partial->find(30);
        $spec->count();
    }

    public function scenario15(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock31', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(31)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(31)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(31)->once()->andReturn('higher');
        $mock->expects()->find(31)->andReturn('expected');
        $mock->allows([])->find(31)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(31)->once();
        $spy->shouldHaveReceived()->find(31)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(31);
        $multi->flush();
        $partial->find(31);
        $spec->count();
    }
}
