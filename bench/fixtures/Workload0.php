<?php

declare(strict_types=1);

namespace MockeryBench;

use Mockery;

final class Workload0
{
    public function scenario0(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock0', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(0)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(0)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(0)->once()->andReturn('higher');
        $mock->expects()->find(0)->andReturn('expected');
        $mock->allows([])->find(0)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(0)->once();
        $spy->shouldHaveReceived()->find(0)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(0);
        $multi->flush();
        $partial->find(0);
        $spec->count();
    }

    public function scenario1(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock1', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(1)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(1)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(1)->once()->andReturn('higher');
        $mock->expects()->find(1)->andReturn('expected');
        $mock->allows([])->find(1)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(1)->once();
        $spy->shouldHaveReceived()->find(1)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(1);
        $multi->flush();
        $partial->find(1);
        $spec->count();
    }

    public function scenario2(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock2', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(2)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(2)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(2)->once()->andReturn('higher');
        $mock->expects()->find(2)->andReturn('expected');
        $mock->allows([])->find(2)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(2)->once();
        $spy->shouldHaveReceived()->find(2)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(2);
        $multi->flush();
        $partial->find(2);
        $spec->count();
    }

    public function scenario3(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock3', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(3)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(3)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(3)->once()->andReturn('higher');
        $mock->expects()->find(3)->andReturn('expected');
        $mock->allows([])->find(3)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(3)->once();
        $spy->shouldHaveReceived()->find(3)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(3);
        $multi->flush();
        $partial->find(3);
        $spec->count();
    }

    public function scenario4(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock4', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(4)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(4)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(4)->once()->andReturn('higher');
        $mock->expects()->find(4)->andReturn('expected');
        $mock->allows([])->find(4)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(4)->once();
        $spy->shouldHaveReceived()->find(4)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(4);
        $multi->flush();
        $partial->find(4);
        $spec->count();
    }

    public function scenario5(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock5', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(5)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(5)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(5)->once()->andReturn('higher');
        $mock->expects()->find(5)->andReturn('expected');
        $mock->allows([])->find(5)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(5)->once();
        $spy->shouldHaveReceived()->find(5)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(5);
        $multi->flush();
        $partial->find(5);
        $spec->count();
    }

    public function scenario6(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock6', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(6)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(6)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(6)->once()->andReturn('higher');
        $mock->expects()->find(6)->andReturn('expected');
        $mock->allows([])->find(6)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(6)->once();
        $spy->shouldHaveReceived()->find(6)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(6);
        $multi->flush();
        $partial->find(6);
        $spec->count();
    }

    public function scenario7(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock7', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(7)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(7)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(7)->once()->andReturn('higher');
        $mock->expects()->find(7)->andReturn('expected');
        $mock->allows([])->find(7)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(7)->once();
        $spy->shouldHaveReceived()->find(7)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(7);
        $multi->flush();
        $partial->find(7);
        $spec->count();
    }

    public function scenario8(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock8', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(8)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(8)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(8)->once()->andReturn('higher');
        $mock->expects()->find(8)->andReturn('expected');
        $mock->allows([])->find(8)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(8)->once();
        $spy->shouldHaveReceived()->find(8)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(8);
        $multi->flush();
        $partial->find(8);
        $spec->count();
    }

    public function scenario9(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock9', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(9)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(9)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(9)->once()->andReturn('higher');
        $mock->expects()->find(9)->andReturn('expected');
        $mock->allows([])->find(9)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(9)->once();
        $spy->shouldHaveReceived()->find(9)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(9);
        $multi->flush();
        $partial->find(9);
        $spec->count();
    }

    public function scenario10(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock10', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(10)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(10)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(10)->once()->andReturn('higher');
        $mock->expects()->find(10)->andReturn('expected');
        $mock->allows([])->find(10)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(10)->once();
        $spy->shouldHaveReceived()->find(10)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(10);
        $multi->flush();
        $partial->find(10);
        $spec->count();
    }

    public function scenario11(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock11', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(11)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(11)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(11)->once()->andReturn('higher');
        $mock->expects()->find(11)->andReturn('expected');
        $mock->allows([])->find(11)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(11)->once();
        $spy->shouldHaveReceived()->find(11)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(11);
        $multi->flush();
        $partial->find(11);
        $spec->count();
    }

    public function scenario12(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock12', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(12)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(12)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(12)->once()->andReturn('higher');
        $mock->expects()->find(12)->andReturn('expected');
        $mock->allows([])->find(12)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(12)->once();
        $spy->shouldHaveReceived()->find(12)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(12);
        $multi->flush();
        $partial->find(12);
        $spec->count();
    }

    public function scenario13(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock13', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(13)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(13)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(13)->once()->andReturn('higher');
        $mock->expects()->find(13)->andReturn('expected');
        $mock->allows([])->find(13)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(13)->once();
        $spy->shouldHaveReceived()->find(13)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(13);
        $multi->flush();
        $partial->find(13);
        $spec->count();
    }

    public function scenario14(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock14', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(14)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(14)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(14)->once()->andReturn('higher');
        $mock->expects()->find(14)->andReturn('expected');
        $mock->allows([])->find(14)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(14)->once();
        $spy->shouldHaveReceived()->find(14)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(14);
        $multi->flush();
        $partial->find(14);
        $spec->count();
    }

    public function scenario15(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock15', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(15)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(15)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(15)->once()->andReturn('higher');
        $mock->expects()->find(15)->andReturn('expected');
        $mock->allows([])->find(15)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(15)->once();
        $spy->shouldHaveReceived()->find(15)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(15);
        $multi->flush();
        $partial->find(15);
        $spec->count();
    }
}
