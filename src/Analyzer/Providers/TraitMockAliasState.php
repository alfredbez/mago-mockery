<?php

declare(strict_types=1);

namespace AlfredBez\MagoMockery\Analyzer\Providers;

use function array_keys;
use function count;
use function implode;

/**
 * @phpstan-type CellValue array{mock: bool, container: bool}
 * @phpstan-type CellRecord array{mock: bool, container: bool, escaped: bool}
 */
final class TraitMockAliasState
{
    public bool $terminated = false;

    /** @var array<string, int> */
    private array $vars = [];

    /** @var array<int, CellRecord> */
    private array $cells = [];

    /** @var array<string, array<string, true>> */
    private array $mayRefs = [];

    private int $nextCell = 1;

    /** @return CellValue */
    public static function unknownValue(): array
    {
        return ['mock' => false, 'container' => false];
    }

    /** @return CellValue */
    public static function mockValue(): array
    {
        return ['mock' => true, 'container' => false];
    }

    /** @return CellValue */
    public static function containerValue(): array
    {
        return ['mock' => false, 'container' => true];
    }

    /**
     * @param CellValue $a
     * @param CellValue $b
     * @return CellValue
     */
    public static function meetValue(array $a, array $b): array
    {
        return [
            'mock' => $a['mock'] && $b['mock'],
            'container' => $a['container'] && $b['container'],
        ];
    }

    /** @return CellValue */
    public function valueOf(string $varName): array
    {
        $cellId = $this->vars[$varName] ?? null;
        if ($cellId === null || !isset($this->cells[$cellId])) {
            return self::unknownValue();
        }

        $cell = $this->cells[$cellId];

        return ['mock' => $cell['mock'], 'container' => $cell['container']];
    }

    /** @param CellValue $value */
    public function assignValue(string $varName, array $value): void
    {
        $cellId = $this->ensureCell($varName);
        $this->cells[$cellId]['mock'] = $value['mock'];
        $this->cells[$cellId]['container'] = $value['container'];

        foreach (array_keys($this->mayRefs[$varName] ?? []) as $peer) {
            $peerCellId = $this->vars[$peer] ?? null;
            if ($peerCellId === null || $peerCellId === $cellId || !isset($this->cells[$peerCellId])) {
                continue;
            }
            $this->cells[$peerCellId]['mock'] = $this->cells[$peerCellId]['mock'] && $value['mock'];
            $this->cells[$peerCellId]['container'] = $this->cells[$peerCellId]['container'] && $value['container'];
        }
    }

    public function assignByRef(string $lhsVar, ?string $rhsVar): void
    {
        if ($lhsVar === $rhsVar) {
            $this->ensureCell($lhsVar);

            return;
        }

        if (
            $rhsVar !== null
            && isset($this->vars[$lhsVar], $this->vars[$rhsVar])
            && $this->vars[$lhsVar] === $this->vars[$rhsVar]
        ) {
            return;
        }

        $this->detachMayRefs($lhsVar);
        unset($this->vars[$lhsVar]);

        if ($rhsVar === null) {
            $cellId = $this->nextCell++;
            $this->cells[$cellId] = ['mock' => false, 'container' => false, 'escaped' => true];
            $this->vars[$lhsVar] = $cellId;

            return;
        }

        $rhsCellId = $this->ensureCell($rhsVar);
        $this->vars[$lhsVar] = $rhsCellId;

        foreach (array_keys($this->mayRefs[$rhsVar] ?? []) as $peer) {
            if ($peer !== $lhsVar) {
                $this->addMayRef($lhsVar, $peer);
            }
        }
    }

    public function markEscaped(string $varName): void
    {
        $cellId = $this->ensureCell($varName);
        $this->cells[$cellId]['escaped'] = true;

        foreach (array_keys($this->mayRefs[$varName] ?? []) as $peer) {
            $peerCellId = $this->vars[$peer] ?? null;
            if ($peerCellId !== null && isset($this->cells[$peerCellId])) {
                $this->cells[$peerCellId]['escaped'] = true;
            }
        }
    }

    public function killEscapedCells(): void
    {
        foreach ($this->cells as $cellId => $cell) {
            if ($cell['escaped']) {
                $this->cells[$cellId]['mock'] = false;
                $this->cells[$cellId]['container'] = false;
            }
        }
    }

    public function unsetVar(string $varName): void
    {
        $this->detachMayRefs($varName);
        unset($this->vars[$varName]);
    }

    public function killVar(string $varName): void
    {
        $this->assignValue($varName, self::unknownValue());
    }

    public function maybeDetachVar(string $varName): void
    {
        $oldCellId = $this->vars[$varName] ?? null;
        if ($oldCellId === null) {
            return;
        }

        $oldEscaped = $this->cells[$oldCellId]['escaped'] ?? false;
        $coAliased = [];
        foreach ($this->vars as $other => $cid) {
            if ($other !== $varName && $cid === $oldCellId) {
                $coAliased[] = $other;
            }
        }

        $newCellId = $this->nextCell++;
        $this->cells[$newCellId] = ['mock' => false, 'container' => false, 'escaped' => $oldEscaped];
        $this->vars[$varName] = $newCellId;

        foreach ($coAliased as $other) {
            $this->addMayRef($varName, $other);
        }
    }

    public function maybeDetachAndKillVar(string $varName): void
    {
        $this->killVar($varName);
        $this->maybeDetachVar($varName);
    }

    public function killAll(): void
    {
        foreach ($this->cells as $cellId => $cell) {
            $this->cells[$cellId]['mock'] = false;
            $this->cells[$cellId]['container'] = false;
        }
    }

    public function replaceWith(self $other): void
    {
        $this->terminated = $other->terminated;
        $this->vars = $other->vars;
        $this->cells = $other->cells;
        $this->mayRefs = $other->mayRefs;
        $this->nextCell = $other->nextCell;
    }

    public function snapshotValuesOnly(): self
    {
        $copy = new self();
        foreach ($this->vars as $varName => $cellId) {
            $cell = $this->cells[$cellId] ?? null;
            if ($cell === null) {
                continue;
            }
            $newId = $copy->nextCell++;
            $copy->cells[$newId] = [
                'mock' => $cell['mock'],
                'container' => $cell['container'],
                'escaped' => false,
            ];
            $copy->vars[$varName] = $newId;
        }

        return $copy;
    }

    /**
     * @param list<self> $branches
     */
    public static function join(array $branches, bool $ignoreTerminated = false): self
    {
        $live = [];
        foreach ($branches as $branch) {
            if ($ignoreTerminated || !$branch->terminated) {
                $live[] = $branch;
            }
        }

        if ($live === []) {
            $dead = new self();
            $dead->terminated = true;

            return $dead;
        }

        if (count($live) === 1) {
            $single = clone $live[0];
            if ($ignoreTerminated) {
                $single->terminated = false;
            }

            return $single;
        }

        /** @var array<string, true> $allVars */
        $allVars = [];
        foreach ($live as $branch) {
            foreach (array_keys($branch->vars) as $varName) {
                $allVars[$varName] = true;
            }
        }

        /** @var array<string, list<string>> $groups */
        $groups = [];
        foreach (array_keys($allVars) as $varName) {
            $parts = [];
            foreach ($live as $branch) {
                $cid = $branch->vars[$varName] ?? null;
                $parts[] = $cid !== null ? 'c' . $cid : 'u:' . $varName;
            }
            $groups[implode('|', $parts)][] = $varName;
        }

        $joined = new self();
        foreach ($groups as $groupVars) {
            $rep = $groupVars[0];
            $mock = true;
            $container = true;
            $escaped = false;

            foreach ($live as $branch) {
                $cid = $branch->vars[$rep] ?? null;
                if ($cid === null || !isset($branch->cells[$cid])) {
                    $mock = false;
                    $container = false;
                    continue;
                }
                $cell = $branch->cells[$cid];
                $mock = $mock && $cell['mock'];
                $container = $container && $cell['container'];
                $escaped = $escaped || $cell['escaped'];
            }

            $newId = $joined->nextCell++;
            $joined->cells[$newId] = ['mock' => $mock, 'container' => $container, 'escaped' => $escaped];
            foreach ($groupVars as $varName) {
                $joined->vars[$varName] = $newId;
            }
        }

        foreach ($live as $branch) {
            foreach ($branch->mayRefs as $u => $peers) {
                foreach (array_keys($peers) as $v) {
                    if (isset($joined->vars[$u], $joined->vars[$v]) && $joined->vars[$u] !== $joined->vars[$v]) {
                        $joined->addMayRef($u, $v);
                    }
                }
            }

            /** @var array<int, list<string>> $byCell */
            $byCell = [];
            foreach ($branch->vars as $varName => $cid) {
                $byCell[$cid][] = $varName;
            }
            foreach ($byCell as $coVars) {
                $n = count($coVars);
                for ($i = 0; $i < $n; ++$i) {
                    for ($j = $i + 1; $j < $n; ++$j) {
                        $u = $coVars[$i];
                        $v = $coVars[$j];
                        if ($joined->vars[$u] !== $joined->vars[$v]) {
                            $joined->addMayRef($u, $v);
                        }
                    }
                }
            }
        }

        return $joined;
    }

    private function ensureCell(string $varName): int
    {
        if (isset($this->vars[$varName])) {
            return $this->vars[$varName];
        }

        $cellId = $this->nextCell++;
        $this->cells[$cellId] = ['mock' => false, 'container' => false, 'escaped' => false];
        $this->vars[$varName] = $cellId;

        return $cellId;
    }

    private function addMayRef(string $a, string $b): void
    {
        if ($a === $b) {
            return;
        }
        $this->mayRefs[$a][$b] = true;
        $this->mayRefs[$b][$a] = true;
    }

    private function detachMayRefs(string $varName): void
    {
        $peers = $this->mayRefs[$varName] ?? null;
        if ($peers === null) {
            return;
        }
        foreach (array_keys($peers) as $peer) {
            if (!isset($this->mayRefs[$peer])) {
                continue;
            }
            unset($this->mayRefs[$peer][$varName]);
            if ($this->mayRefs[$peer] === []) {
                unset($this->mayRefs[$peer]);
            }
        }
        unset($this->mayRefs[$varName]);
    }
}
