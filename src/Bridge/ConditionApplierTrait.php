<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Bridge;

use Derafu\Query\Builder\Sql\SqlBuilderWhere;
use Derafu\Query\Builder\Sql\SqlSanitizerTrait;
use Derafu\Query\Filter\Contract\CompositeConditionInterface;
use Derafu\Query\Filter\Contract\ConditionInterface;
use Derafu\Query\Filter\Contract\PathInterface;

/**
 * Shared logic for condition applier implementations.
 *
 * Provides SQL compilation, path extraction, FROM inference, and JOIN spec
 * building. QB-specific operations (setting FROM, adding JOINs, applying WHERE/
 * HAVING) are left to the consuming class.
 */
trait ConditionApplierTrait
{
    use SqlSanitizerTrait;

    /**
     * Cache de instancias SqlBuilderWhere por driver. SqlBuilderWhere es
     * stateless (solo almacena engine y listDelimiter como readonly), por lo
     * que la misma instancia puede reutilizarse indefinidamente para el mismo
     * driver. En la práctica habrá como máximo una entrada por tipo de BD.
     *
     * @var array<string, SqlBuilderWhere>
     */
    private array $sqlBuilderCache = [];

    /**
     * Compiles a condition tree into a SQL fragment and its named parameters.
     *
     * @param string $parentAlias Alias (or name) of the FROM table, required
     *        when the condition tree contains EXISTS subquery paths (___).
     * @return array{sql: string, parameters: array<string, mixed>}
     */
    protected function buildConditionSql(
        string $driver,
        ConditionInterface|CompositeConditionInterface $condition,
        string $parentAlias = ''
    ): array {
        $this->sqlBuilderCache[$driver] ??= new SqlBuilderWhere($driver);

        return $this->sqlBuilderCache[$driver]->build($condition, $parentAlias)->getQuery();
    }

    /**
     * Recursively collects all PathInterface objects from a condition tree.
     *
     * @return PathInterface[]
     */
    protected function extractPaths(
        ConditionInterface|CompositeConditionInterface $condition
    ): array {
        if ($condition instanceof ConditionInterface) {
            return [$condition->getPath()];
        }

        $paths = [];
        foreach ($condition->getConditions() as $sub) {
            $paths = array_merge($paths, $this->extractPaths($sub));
        }

        return $paths;
    }

    /**
     * Infers the base table from the first multi-segment path found.
     *
     * Returns null when all paths are single-segment (no join information).
     *
     * @param PathInterface[] $paths
     * @return array{table: string, alias: string|null}|null
     */
    protected function inferFromTable(array $paths): ?array
    {
        foreach ($paths as $path) {
            $segments = $path->getSegments();
            if (count($segments) > 1) {
                $first = $segments[0];
                $alias = $first->getOption('alias');
                return [
                    'table' => $this->sanitizeSqlSimpleIdentifier($first->getName()),
                    'alias' => $alias
                        ? $this->sanitizeSqlSimpleIdentifier($alias)
                        : null,
                ];
            }
        }

        return null;
    }

    /**
     * Builds ORM JOIN specifications from path segments for Doctrine ORM DQL.
     *
     * Unlike the SQL variant, no ON conditions are required — Doctrine derives
     * the join condition from the entity association mapping. Skips paths with
     * only one segment (no join needed).
     *
     * Each spec:
     *   - type:  'inner' | 'left'
     *   - join:  DQL join target, e.g. "c.invoices"
     *   - alias: alias for the joined entity
     *
     * @param PathInterface[] $paths
     * @return array<int, array{type: string, join: string, alias: string}>
     */
    protected function buildOrmJoinSpecsFromPaths(array $paths): array
    {
        $specs = [];
        $seen = [];

        foreach ($paths as $path) {
            $segments = $path->getSegments();

            if (count($segments) <= 1) {
                continue;
            }

            $baseSegment = $segments[0];
            $previousAlias = $baseSegment->getOption('alias') ?? $baseSegment->getName();

            for ($i = 1; $i < count($segments) - 1; $i++) {
                $segment = $segments[$i];
                $assoc = $segment->getName();
                $alias = $segment->getOption('alias') ?? $assoc;
                $joinType = strtolower($segment->getOption('join', 'inner'));

                if (!isset($seen[$alias])) {
                    $seen[$alias] = true;
                    $specs[] = [
                        'type' => $joinType,
                        'join' => $previousAlias . '.' . $assoc,
                        'alias' => $alias,
                    ];
                }

                $previousAlias = $alias;
            }
        }

        return $specs;
    }

    /**
     * Builds JOIN specifications from path segments without touching any QB.
     *
     * Skips paths whose base table does not match $currentFromTable.
     * Skips intermediate segments that carry no ON conditions.
     *
     * Each spec:
     *   - type:      'inner' | 'left' | 'right'
     *   - fromAlias: alias (or name) of the driving table/alias
     *   - table:     join target table name (sanitized)
     *   - alias:     alias for the join target (falls back to table name)
     *   - tableRef:  "table" or "table as alias" string (Illuminate style)
     *   - condition: ON clause string, e.g. "a.id = b.fk"
     *
     * @param PathInterface[] $paths
     * @param string $currentFromTable Lower-case FROM table name (or alias).
     * @return array<int, array{type: string, fromAlias: string, table: string, alias: string, tableRef: string, condition: string}>
     */
    protected function buildJoinSpecsFromPaths(array $paths, string $currentFromTable): array
    {
        $specs = [];

        foreach ($paths as $path) {
            $segments = $path->getSegments();

            if (count($segments) <= 1) {
                continue;
            }

            $baseSegment = $segments[0];
            $baseTable = $this->sanitizeSqlSimpleIdentifier($baseSegment->getName());

            if (!empty($currentFromTable) && strtolower($currentFromTable) !== strtolower($baseTable)) {
                continue;
            }

            $previousAlias = $baseSegment->getOption('alias') ?? $baseSegment->getName();

            for ($i = 1; $i < count($segments) - 1; $i++) {
                $segment = $segments[$i];
                $targetTable = $this->sanitizeSqlSimpleIdentifier($segment->getName());
                $targetAlias = $segment->getOption('alias');
                $joinType = strtolower($segment->getOption('join', 'inner'));

                $joinConditions = $segment->getOption('on');
                if (!$joinConditions) {
                    $previousAlias = $targetAlias ?? $targetTable;
                    continue;
                }

                $parts = [];
                foreach ($joinConditions as $sourceCol => $targetCol) {
                    $parts[] = sprintf(
                        '%s.%s = %s.%s',
                        $this->sanitizeSqlSimpleIdentifier($previousAlias),
                        $this->sanitizeSqlSimpleIdentifier($sourceCol),
                        $this->sanitizeSqlSimpleIdentifier($targetAlias ?? $targetTable),
                        $this->sanitizeSqlSimpleIdentifier($targetCol)
                    );
                }

                $tableRef = $targetAlias
                    ? $targetTable . ' as ' . $this->sanitizeSqlSimpleIdentifier($targetAlias)
                    : $targetTable;

                $specs[] = [
                    'type' => $joinType,
                    'fromAlias' => $previousAlias,
                    'table' => $targetTable,
                    'alias' => $targetAlias ?? $targetTable,
                    'tableRef' => $tableRef,
                    'condition' => implode(' AND ', $parts),
                ];

                $previousAlias = $targetAlias ?? $targetTable;
            }
        }

        return $specs;
    }
}
