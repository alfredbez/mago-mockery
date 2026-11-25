<?php

declare(strict_types=1);

namespace AlfredBez\MagoMockery\Analyzer\Providers;

use function array_keys;
use function count;
use function implode;

/**
 * @phpstan-type CellValue array{mock: bool, container: bool, null: bool, mockOrNull: bool}
 * @phpstan-type CellRecord array{mock: bool, container: bool, null: bool, mockOrNull: bool, escaped: bool, staticLocal: bool}
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
        return ['mock' => false, 'container' => false, 'null' => false, 'mockOrNull' => false];
    }

    /** @return CellValue */
    public static function mockValue(): array
    {
        return ['mock' => true, 'container' => false, 'null' => false, 'mockOrNull' => true];
    }

    /** @return CellValue */
    public static function containerValue(): array
    {
        return ['mock' => false, 'container' => true, 'null' => false, 'mockOrNull' => false];
    }

    /** @return CellValue */
    public static function nullValue(): array
    {
        return ['mock' => false, 'container' => false, 'null' => true, 'mockOrNull' => true];
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
            'null' => $a['null'] && $b['null'],
            'mockOrNull' => $a['mockOrNull'] && $b['mockOrNull'],
        ];
    }

    /** @return CellValue */
    public function valueOf(string $varName): array
    {
        $cellId = $this->vars[$varName] ?? null;
        if ($cellId === null || !isset($this->cells[$cellId])) {
            return self::unknownValue();
        }

        return self::extractValue($this->cells[$cellId]);
    }

    /** @param CellValue $value */
    public function assignValue(string $varName, array $value): void
    {
        $cellId = $this->ensureCell($varName);
        $this->setCellValue($cellId, $value);

        foreach (array_keys($this->mayRefs[$varName] ?? []) as $peer) {
            $peerCellId = $this->vars[$peer] ?? null;
            if ($peerCellId === null || $peerCellId === $cellId || !isset($this->cells[$peerCellId])) {
                continue;
            }
            $this->setCellValue($peerCellId, self::meetValue(self::extractValue($this->cells[$peerCellId]), $value));
        }
    }

    /** @param CellValue $value */
    public function refineCellValue(string $varName, array $value): void
    {
        $cellId = $this->ensureCell($varName);
        $this->setCellValue($cellId, $value);
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
            $this->cells[$cellId] = self::makeCell(self::unknownValue(), true, false);
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

    public function bindStaticLocal(string $varName): void
    {
        $this->detachMayRefs($varName);
        unset($this->vars[$varName]);

        $cellId = $this->nextCell++;
        $this->cells[$cellId] = self::makeCell(self::unknownValue(), false, true);
        $this->vars[$varName] = $cellId;
    }

    public function markStaticLocal(string $varName): void
    {
        $cellId = $this->ensureCell($varName);
        $this->cells[$cellId]['staticLocal'] = true;

        foreach (array_keys($this->mayRefs[$varName] ?? []) as $peer) {
            $peerCellId = $this->vars[$peer] ?? null;
            if ($peerCellId !== null && isset($this->cells[$peerCellId])) {
                $this->cells[$peerCellId]['staticLocal'] = true;
            }
        }
    }

    public function hasStaticLocalCells(): bool
    {
        foreach ($this->cells as $cell) {
            if ($cell['staticLocal']) {
                return true;
            }
        }

        return false;
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
        $unknown = self::unknownValue();
        foreach ($this->cells as $cellId => $cell) {
            if ($cell['escaped']) {
                $this->setCellValue($cellId, $unknown);
            }
        }
    }

    public function killStaticLocalCells(): void
    {
        $unknown = self::unknownValue();
        foreach ($this->cells as $cellId => $cell) {
            if ($cell['staticLocal']) {
                $this->setCellValue($cellId, $unknown);
            }
        }
    }

    public function resetVar(string $varName): void
    {
        $this->detachMayRefs($varName);
        unset($this->vars[$varName]);
    }

    public function unsetVar(string $varName): void
    {
        $this->detachMayRefs($varName);
        unset($this->vars[$varName]);

        $cellId = $this->nextCell++;
        $this->cells[$cellId] = self::makeCell(self::nullValue(), false, false);
        $this->vars[$varName] = $cellId;
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
        $oldStaticLocal = $this->cells[$oldCellId]['staticLocal'] ?? false;
        $coAliased = [];
        foreach ($this->vars as $other => $cid) {
            if ($other !== $varName && $cid === $oldCellId) {
                $coAliased[] = $other;
            }
        }

        $newCellId = $this->nextCell++;
        $this->cells[$newCellId] = self::makeCell(self::unknownValue(), $oldEscaped, $oldStaticLocal);
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
        $unknown = self::unknownValue();
        foreach (array_keys($this->cells) as $cellId) {
            $this->setCellValue($cellId, $unknown);
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
            $copy->cells[$newId] = self::makeCell(self::extractValue($cell), false, false);
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
            $value = null;
            $escaped = false;
            $staticLocal = false;

            foreach ($live as $branch) {
                $cid = $branch->vars[$rep] ?? null;
                if ($cid === null || !isset($branch->cells[$cid])) {
                    $branchVal = self::unknownValue();
                } else {
                    $cell = $branch->cells[$cid];
                    $branchVal = self::extractValue($cell);
                    $escaped = $escaped || $cell['escaped'];
                    $staticLocal = $staticLocal || $cell['staticLocal'];
                }
                $value = $value === null ? $branchVal : self::meetValue($value, $branchVal);
            }

            $newId = $joined->nextCell++;
            $joined->cells[$newId] = self::makeCell($value ?? self::unknownValue(), $escaped, $staticLocal);
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

    /**
     * @param CellRecord $cell
     * @return CellValue
     */
    private static function extractValue(array $cell): array
    {
        return [
            'mock' => $cell['mock'],
            'container' => $cell['container'],
            'null' => $cell['null'],
            'mockOrNull' => $cell['mockOrNull'],
        ];
    }

    /**
     * @param CellValue $value
     * @return CellRecord
     */
    private static function makeCell(array $value, bool $escaped, bool $staticLocal): array
    {
        return [
            'mock' => $value['mock'],
            'container' => $value['container'],
            'null' => $value['null'],
            'mockOrNull' => $value['mockOrNull'],
            'escaped' => $escaped,
            'staticLocal' => $staticLocal,
        ];
    }

    /** @param CellValue $value */
    private function setCellValue(int $cellId, array $value): void
    {
        $this->cells[$cellId]['mock'] = $value['mock'];
        $this->cells[$cellId]['container'] = $value['container'];
        $this->cells[$cellId]['null'] = $value['null'];
        $this->cells[$cellId]['mockOrNull'] = $value['mockOrNull'];
    }

    private function ensureCell(string $varName): int
    {
        if (isset($this->vars[$varName])) {
            return $this->vars[$varName];
        }

        $cellId = $this->nextCell++;
        $this->cells[$cellId] = self::makeCell(self::unknownValue(), false, false);
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
