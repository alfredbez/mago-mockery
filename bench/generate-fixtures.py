from pathlib import Path


def generate():
    for file_index in range(4):
        methods = []
        for index in range(16):
            item_id = file_index * 16 + index
            methods.append(f"""    public function scenario{index}(): void
    {{
        $mock = Mockery::mock(Repository::class);
        $spy = Mockery::spy(Repository::class);
        $named = Mockery::namedMock('RepositoryMock{item_id}', Repository::class);
        $multi = Mockery::mock(Repository::class . ', ' . Flushable::class);
        $partial = Mockery::mock(new RepositoryImpl());
        $spec = Mockery::mock(RepositoryImpl::class . '[find]');
        $mock->shouldReceive('find')->with({item_id})->once()->andReturn('row');
        $mock->shouldReceive('count')->withNoArgs()->twice()->andReturn(2);
        $named->expects('find')->with({item_id})->times(2)->andReturn('named');
        $mock->allows('find')->withAnyArgs()->between(1, 3)->andReturn('allowed');
        $mock->shouldReceive()->find({item_id})->once()->andReturn('higher');
        $mock->expects()->find({item_id})->andReturn('expected');
        $mock->allows([])->find({item_id})->andReturn('empty');
        $spy->shouldHaveReceived('find')->with({item_id})->once();
        $spy->shouldHaveReceived()->find({item_id})->twice();
        $spy->shouldNotHaveReceived('count');
        $spy->shouldNotHaveReceived()->count();
        $mock->shouldReceive('find')->andReturn('row')->shouldReceive('count')->andReturn(1)->getMock()->find({item_id});
        $multi->flush();
        $partial->find({item_id});
        $spec->count();
    }}
""")
        content = "<?php\n\ndeclare(strict_types=1);\n\nnamespace MockeryBench;\n\nuse Mockery;\n\n"
        content += f"final class Workload{file_index}\n{{\n" + "\n".join(methods) + "}\n"
        (Path(__file__).parent / "fixtures" / f"Workload{file_index}.php").write_text(content)


if __name__ == "__main__":
    generate()
