<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Bridge;

use Derafu\Query\Bridge\Contract\QueryBuilderConditionApplierInterface;
use Derafu\Query\Builder\Sql\SqlBuilderWhere;
use Derafu\Query\Builder\Sql\SqlSanitizerTrait;
use Derafu\Query\Filter\Contract\CompositeConditionInterface;
use Derafu\Query\Filter\Contract\ConditionInterface;
use Derafu\Query\Filter\Contract\PathInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Applies parsed conditions to an Illuminate Query Builder.
 *
 * Accepts both Illuminate\Database\Query\Builder and
 * Illuminate\Database\Eloquent\Builder — Eloquent builders are resolved
 * to their underlying Query\Builder via toBase() automatically.
 *
 * Handles WHERE clause generation, FROM inference from path segments,
 * and JOIN application (with deduplication). Uses SqlBuilderWhere
 * internally to generate driver-specific SQL fragments.
 */
final class IlluminateQueryBuilderConditionApplier implements QueryBuilderConditionApplierInterface
{
    use SqlSanitizerTrait;

    /**
     * {@inheritDoc}
     */
    public function apply(
        object $queryBuilder,
        ConditionInterface|CompositeConditionInterface $condition
    ): void {
        $qb = $this->resolveBuilder($queryBuilder);

        $this->processFromAndJoins($qb, $condition);

        ['sql' => $sql, 'parameters' => $params] = $this->buildConditionSql($qb, $condition);

        $qb->whereRaw($sql, $params);
    }

    /**
     * {@inheritDoc}
     */
    public function applyHaving(
        object $queryBuilder,
        ConditionInterface|CompositeConditionInterface $condition
    ): void {
        $qb = $this->resolveBuilder($queryBuilder);

        ['sql' => $sql, 'parameters' => $params] = $this->buildConditionSql($qb, $condition);

        $qb->havingRaw($sql, $params);
    }

    /**
     * Resolves both Query\Builder and Eloquent\Builder to Query\Builder.
     *
     * For Eloquent builders, toBase() applies pending scopes and returns
     * the shared underlying Query\Builder instance, so mutations made here
     * are reflected in the original Eloquent builder.
     */
    private function resolveBuilder(object $queryBuilder): QueryBuilder
    {
        if ($queryBuilder instanceof EloquentBuilder) {
            return $queryBuilder->toBase();
        }

        assert($queryBuilder instanceof QueryBuilder);

        return $queryBuilder;
    }

    /**
     * Compiles a condition tree into a SQL fragment and its parameters.
     *
     * @return array{sql: string, parameters: array}
     */
    private function buildConditionSql(
        QueryBuilder $qb,
        ConditionInterface|CompositeConditionInterface $condition
    ): array {
        $connection = $qb->getConnection();
        assert($connection instanceof \Illuminate\Database\Connection);
        $driver = $connection->getDriverName();

        return (new SqlBuilderWhere($driver))->build($condition)->getQuery();
    }

    /**
     * Infers the base table from path segments (if FROM not yet set)
     * and applies all JOIN clauses found in the condition paths.
     */
    private function processFromAndJoins(
        QueryBuilder $qb,
        ConditionInterface|CompositeConditionInterface $condition
    ): void {
        $paths = $this->extractPaths($condition);

        $currentFrom = property_exists($qb, 'from') ? $qb->from : null;

        if (empty($currentFrom)) {
            foreach ($paths as $path) {
                $segments = $path->getSegments();
                if (count($segments) > 1) {
                    $first = $segments[0];
                    $table = $this->sanitizeSqlSimpleIdentifier($first->getName());
                    $alias = $first->getOption('alias');
                    $qb->from(
                        $table,
                        $alias ? $this->sanitizeSqlSimpleIdentifier($alias) : null
                    );
                    break;
                }
            }
        }

        foreach ($paths as $path) {
            $this->applyJoinsFromPath($qb, $path);
        }
    }

    /**
     * Applies JOINs derived from a multi-segment path.
     * Skips joins that were already added to the query builder.
     */
    private function applyJoinsFromPath(
        QueryBuilder $qb,
        PathInterface $path
    ): void {
        $segments = $path->getSegments();

        if (count($segments) <= 1) {
            return;
        }

        $baseSegment = $segments[0];
        $baseTable = $this->sanitizeSqlSimpleIdentifier($baseSegment->getName());

        $currentFrom = property_exists($qb, 'from') ? (string)$qb->from : '';
        $fromTable = strtolower(trim(preg_replace('/\s+as\s+\S+$/i', '', $currentFrom)));

        if (!empty($fromTable) && $fromTable !== strtolower($baseTable)) {
            return;
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
            $onCondition = implode(' AND ', $parts);

            $tableRef = $targetAlias
                ? $targetTable . ' as ' . $this->sanitizeSqlSimpleIdentifier($targetAlias)
                : $targetTable;

            if (!$this->hasJoin($qb, $tableRef)) {
                match ($joinType) {
                    'left' => $qb->leftJoin(
                        $tableRef,
                        fn ($join) => $join->whereRaw($onCondition)
                    ),
                    'right' => $qb->rightJoin(
                        $tableRef,
                        fn ($join) => $join->whereRaw($onCondition)
                    ),
                    default => $qb->join(
                        $tableRef,
                        fn ($join) => $join->whereRaw($onCondition)
                    ),
                };
            }

            $previousAlias = $targetAlias ?? $targetTable;
        }
    }

    /**
     * Checks if a table reference is already joined in the query builder.
     */
    private function hasJoin(QueryBuilder $qb, string $tableRef): bool
    {
        $joins = property_exists($qb, 'joins') ? $qb->joins : null;
        if (empty($joins)) {
            return false;
        }
        foreach ($joins as $join) {
            if (strtolower($join->table) === strtolower($tableRef)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Recursively collects all path objects from a condition tree.
     *
     * @return PathInterface[]
     */
    private function extractPaths(
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
}
