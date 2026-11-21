import argparse
from collections import Counter
from pathlib import Path
import re


def summarize(path):
    names = {}
    own = Counter()
    inclusive = Counter()
    calls = Counter()
    current = None
    callee = None
    edge_cost = False
    with path.open() as trace:
        for line in trace:
            if line.startswith(("fn=", "cfn=")):
                match = re.match(r"c?fn=\((\d+)\)(?: (.*))?", line.rstrip())
                number, name = match.groups()
                if name is not None:
                    names[number] = name
                if line.startswith("fn="):
                    current = names[number]
                    edge_cost = False
                else:
                    callee = names[number]
            elif line.startswith("calls="):
                calls[callee] += int(line.split()[0].removeprefix("calls="))
                edge_cost = True
            elif line[:1].isdigit() and current is not None:
                ticks = int(line.split()[1])
                inclusive[current] += ticks
                if not edge_cost:
                    own[current] += ticks
                edge_cost = False
    print(f"\n{path.name}: Xdebug instrumented times, not benchmark timings")
    print(f"{'self ms':>12} {'inclusive ms':>14} {'calls':>10} function")
    for name, ticks in own.most_common(20):
        print(f"{ticks / 100000:12.3f} {inclusive[name] / 100000:14.3f} {calls[name]:10d} {name}")


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Summarize uncompressed Xdebug callgrind profiles.")
    parser.add_argument("profiles", nargs="+", type=Path)
    for profile in parser.parse_args().profiles:
        summarize(profile)
