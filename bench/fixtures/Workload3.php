<?php

declare(strict_types=1);

namespace MockeryBench;

use Mockery;

final class Workload3
{
    public function scenario0(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock48', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(48)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(48)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(48)->once()->andReturn('higher');
        $mock->expects()->find(48)->andReturn('expected');
        $mock->allows([])->find(48)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(48)->once();
        $spy->shouldHaveReceived()->find(48)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(48);
        $multi->flush();
        $partial->find(48);
        $spec->count();
    }

    public function scenario1(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock49', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(49)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(49)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(49)->once()->andReturn('higher');
        $mock->expects()->find(49)->andReturn('expected');
        $mock->allows([])->find(49)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(49)->once();
        $spy->shouldHaveReceived()->find(49)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(49);
        $multi->flush();
        $partial->find(49);
        $spec->count();
    }

    public function scenario2(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock50', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(50)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(50)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(50)->once()->andReturn('higher');
        $mock->expects()->find(50)->andReturn('expected');
        $mock->allows([])->find(50)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(50)->once();
        $spy->shouldHaveReceived()->find(50)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(50);
        $multi->flush();
        $partial->find(50);
        $spec->count();
    }

    public function scenario3(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock51', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(51)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(51)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(51)->once()->andReturn('higher');
        $mock->expects()->find(51)->andReturn('expected');
        $mock->allows([])->find(51)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(51)->once();
        $spy->shouldHaveReceived()->find(51)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(51);
        $multi->flush();
        $partial->find(51);
        $spec->count();
    }

    public function scenario4(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock52', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(52)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(52)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(52)->once()->andReturn('higher');
        $mock->expects()->find(52)->andReturn('expected');
        $mock->allows([])->find(52)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(52)->once();
        $spy->shouldHaveReceived()->find(52)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(52);
        $multi->flush();
        $partial->find(52);
        $spec->count();
    }

    public function scenario5(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock53', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(53)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(53)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(53)->once()->andReturn('higher');
        $mock->expects()->find(53)->andReturn('expected');
        $mock->allows([])->find(53)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(53)->once();
        $spy->shouldHaveReceived()->find(53)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(53);
        $multi->flush();
        $partial->find(53);
        $spec->count();
    }

    public function scenario6(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock54', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(54)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(54)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(54)->once()->andReturn('higher');
        $mock->expects()->find(54)->andReturn('expected');
        $mock->allows([])->find(54)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(54)->once();
        $spy->shouldHaveReceived()->find(54)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(54);
        $multi->flush();
        $partial->find(54);
        $spec->count();
    }

    public function scenario7(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock55', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(55)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(55)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(55)->once()->andReturn('higher');
        $mock->expects()->find(55)->andReturn('expected');
        $mock->allows([])->find(55)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(55)->once();
        $spy->shouldHaveReceived()->find(55)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(55);
        $multi->flush();
        $partial->find(55);
        $spec->count();
    }

    public function scenario8(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock56', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(56)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(56)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(56)->once()->andReturn('higher');
        $mock->expects()->find(56)->andReturn('expected');
        $mock->allows([])->find(56)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(56)->once();
        $spy->shouldHaveReceived()->find(56)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(56);
        $multi->flush();
        $partial->find(56);
        $spec->count();
    }

    public function scenario9(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock57', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(57)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(57)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(57)->once()->andReturn('higher');
        $mock->expects()->find(57)->andReturn('expected');
        $mock->allows([])->find(57)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(57)->once();
        $spy->shouldHaveReceived()->find(57)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(57);
        $multi->flush();
        $partial->find(57);
        $spec->count();
    }

    public function scenario10(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock58', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(58)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(58)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(58)->once()->andReturn('higher');
        $mock->expects()->find(58)->andReturn('expected');
        $mock->allows([])->find(58)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(58)->once();
        $spy->shouldHaveReceived()->find(58)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(58);
        $multi->flush();
        $partial->find(58);
        $spec->count();
    }

    public function scenario11(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock59', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(59)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(59)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(59)->once()->andReturn('higher');
        $mock->expects()->find(59)->andReturn('expected');
        $mock->allows([])->find(59)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(59)->once();
        $spy->shouldHaveReceived()->find(59)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(59);
        $multi->flush();
        $partial->find(59);
        $spec->count();
    }

    public function scenario12(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock60', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(60)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(60)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(60)->once()->andReturn('higher');
        $mock->expects()->find(60)->andReturn('expected');
        $mock->allows([])->find(60)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(60)->once();
        $spy->shouldHaveReceived()->find(60)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(60);
        $multi->flush();
        $partial->find(60);
        $spec->count();
    }

    public function scenario13(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock61', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(61)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(61)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(61)->once()->andReturn('higher');
        $mock->expects()->find(61)->andReturn('expected');
        $mock->allows([])->find(61)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(61)->once();
        $spy->shouldHaveReceived()->find(61)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(61);
        $multi->flush();
        $partial->find(61);
        $spec->count();
    }

    public function scenario14(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock62', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(62)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(62)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(62)->once()->andReturn('higher');
        $mock->expects()->find(62)->andReturn('expected');
        $mock->allows([])->find(62)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(62)->once();
        $spy->shouldHaveReceived()->find(62)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(62);
        $multi->flush();
        $partial->find(62);
        $spec->count();
    }

    public function scenario15(): void
    {
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock63', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with(63)->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with(63)->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find(63)->once()->andReturn('higher');
        $mock->expects()->find(63)->andReturn('expected');
        $mock->allows([])->find(63)->andReturn('empty');
        $spy->shouldHaveReceived('find')->with(63)->once();
        $spy->shouldHaveReceived()->find(63)->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find(63);
        $multi->flush();
        $partial->find(63);
        $spec->count();
    }
}
