import os
from pathlib import Path
import subprocess

import pytest


ROOT = Path(__file__).resolve().parents[1]
PHP = os.environ.get("PHP_BINARY", "php")
TASKS = [
    "analyze",
    "factory_class",
    "factory_multiple",
    "factory_partial",
    "factory_unknown",
    "signature_with",
    "signature_times",
    "signature_once",
    "signature_andset",
    "signature_missing",
    "signature_add",
    "signature_foreign",
    "composite_with",
    "composite_times",
    "composite_once",
    "composite_andset",
    "composite_missing",
    "composite_add",
    "composite_wrap",
    "composite_extract",
    "higher_order",
    "no_argument",
    "verification",
]


@pytest.mark.parametrize("task", TASKS)
@pytest.mark.benchmark(max_rounds=10)
def test_benchmarks(benchmark, task):
    command = [PHP, "bench/run-local.php", f"--task={task}", "--iterations=20000"]
    benchmark(
        subprocess.run,
        command,
        cwd=ROOT,
        check=True,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        timeout=120,
    )
