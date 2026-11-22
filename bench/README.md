# Run benchmarks

The fixtures contain 64 independent scenarios with factory calls, string specs,
object partials, expectation chains, higher-order messages, and verification chains.
Regenerate them with `python3 bench/generate-fixtures.py`.

Run the local measurements from the repository root with PHP 8.4 and Mago 1.60.2+:

```bash
docker run --rm \
  -v "$PWD:/work" \
  -v /path/to/mago:/opt/mago:ro \
  -w /work php:8.4-cli \
  php bench/run-local.php --runs=7 --json=bench/baseline.json
```

Repeat after a change with `--compare=bench/baseline.json --json=bench/after.json`.
The runner reports median, minimum, maximum, and each raw sample in milliseconds.
Each workload gets an unmeasured warmup. Provider samples contain 20,000 calls by
default. Use the same `--iterations` value in both measurements.

`analyze` starts a fresh analyzer and extension worker for each sample, using one
analyzer thread and one extension worker. It fails if any fixture reports an issue.
The analyzer measurement includes process startup, parsing, metadata, and IPC.
Provider measurements run in the current PHP process with prebuilt contexts and
a warmed SDK metadata cache. They exclude IPC and measure repeated inputs.
The setup uses internal SDK classes pinned by `composer.lock`.

To profile a provider, enable Xdebug's profiler and run a task batch:

```bash
php -d xdebug.mode=profile -d xdebug.start_with_request=yes \
  -d xdebug.use_compression=0 -d xdebug.output_dir=/tmp \
  bench/run-local.php --task=signature_andset
python3 bench/profile-summary.py /tmp/callgrind.out.*
```

Xdebug changes execution costs. Use its call counts and time distribution to
locate work, then compare timings with the profiler disabled.

Use `--only=analyze,factory_class` to select workloads. `--task=factory_class`
runs one batch without the local timer or warmup. The pytest integration uses
this mode and includes PHP startup and context construction in walltime results.
For small providers that overhead can dominate. Compare local provider timings
when evaluating small changes.

Install `bench/requirements.txt` and run the CodSpeed benchmarks with:

```bash
python3 -m pip install -r bench/requirements.txt
python3 -B -m pytest -p no:cacheprovider bench/test_benchmarks.py --codspeed --codspeed-mode=walltime
```

Set `PHP_BINARY` when PHP is outside `PATH`, and `MAGO_BIN` when the Composer
entrypoint needs an explicit binary path. CodSpeed uses `codspeed.yml` and the
workflow's OpenID Connect authentication. Enable the repository in CodSpeed
before expecting uploaded comparisons.

The [pytest-codspeed reference](https://codspeed.io/docs/reference/pytest-codspeed)
documents the benchmark fixture and walltime options.
