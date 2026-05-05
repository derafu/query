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

use Derafu\Query\Bridge\Contract\QueryBuilderConditionApplierInterface;
use Derafu\Query\Filter\Contract\CompositeConditionInterface;
use Derafu\Query\Filter\Contract\ConditionInterface;
use Illuminate\Database\Connection;
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
    use ConditionApplierTrait;

    /**
     * {@inheritDoc}
     */
    public function apply(
        object $queryBuilder,
        ConditionInterface|CompositeConditionInterface $condition
    ): void {
        $qb = $this->resolveBuilder($queryBuilder);

        $this->processFromAndJoins($qb, $condition);

        ['sql' => $sql, 'parameters' => $params] = $this->buildConditionSql(
            $this->resolveDriver($qb),
            $condition,
            $this->getFromAliasOrTable($qb)
        );

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

        ['sql' => $sql, 'parameters' => $params] = $this->buildConditionSql(
            $this->resolveDriver($qb),
            $condition
        );

        $qb->havingRaw($sql, $params);
    }

    /**
     * Returns the driver name string used by SqlBuilderWhere.
     *
     * getConnection() is typed ConnectionInterface which does not declare
     * getDriverName(); asserting the concrete Connection class resolves this.
     */
    private function resolveDriver(QueryBuilder $qb): string
    {
        $connection = $qb->getConnection();
        assert($connection instanceof Connection);
        return $connection->getDriverName();
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
     * Infers the base table from path segments (if FROM not yet set)
     * and applies all JOIN clauses found in the condition paths.
     */
    private function processFromAndJoins(
        QueryBuilder $qb,
        ConditionInterface|CompositeConditionInterface $condition
    ): void {
        $paths = $this->extractPaths($condition);

        $currentFrom = property_exists($qb, 'from') ? (string)$qb->from : '';
        $fromTable = strtolower(trim(preg_replace('/\s+as\s+\S+$/i', '', $currentFrom) ?? ''));

        if (empty($fromTable)) {
            $fromSpec = $this->inferFromTable($paths);
            if ($fromSpec !== null) {
                $qb->from($fromSpec['table'], $fromSpec['alias']);
                $fromTable = strtolower($fromSpec['table']);
            }
        }

        foreach ($this->buildJoinSpecsFromPaths($paths, $fromTable) as $spec) {
            if (!$this->hasJoin($qb, $spec['tableRef'])) {
                match ($spec['type']) {
                    'left' => $qb->leftJoin(
                        $spec['tableRef'],
                        fn ($join) => $join->whereRaw($spec['condition'])
                    ),
                    'right' => $qb->rightJoin(
                        $spec['tableRef'],
                        fn ($join) => $join->whereRaw($spec['condition'])
                    ),
                    default => $qb->join(
                        $spec['tableRef'],
                        fn ($join) => $join->whereRaw($spec['condition'])
                    ),
                };
            }
        }
    }

    /**
     * Returns the alias (if any) or raw table name from the FROM clause,
     * lower-cased. Used as parentAlias in EXISTS correlation conditions.
     *
     * Illuminate stores FROM as a plain string like "invoices" or
     * "invoices as i", so we extract the alias when present.
     */
    private function getFromAliasOrTable(QueryBuilder $qb): string
    {
        $from = property_exists($qb, 'from') ? (string)$qb->from : '';

        if ($from === '') {
            return '';
        }

        if (preg_match('/\s+as\s+(\S+)$/i', $from, $m)) {
            return strtolower($m[1]);
        }

        return strtolower($from);
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
}
