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
use Derafu\Query\Bridge\Illuminate\CompiledCondition;
use Derafu\Query\Filter\Contract\CompositeConditionInterface;
use Derafu\Query\Filter\Contract\ConditionInterface;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Applies parsed conditions to an Illuminate Query Builder.
 *
 * Accepts both Illuminate\Database\Query\Builder and
 * Illuminate\Database\Eloquent\Builder — Eloquent builders are resolved
 * to their underlying Query\Builder via getQuery() automatically.
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

        $compiled = new CompiledCondition($sql, $params);
        $qb->where($compiled);
        $qb->addBinding($compiled->getBindings(), 'where');
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

        $compiled = new CompiledCondition($sql, $params);
        $qb->having($compiled);
        $qb->addBinding($compiled->getBindings(), 'having');
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
     * getQuery() returns the original underlying builder so conditions remain
     * attached to the consumer's query. Eloquent applies pending scopes when
     * executing it; toBase() would instead apply them to a clone.
     */
    private function resolveBuilder(object $queryBuilder): QueryBuilder
    {
        if ($queryBuilder instanceof EloquentBuilder) {
            return $queryBuilder->getQuery();
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

        $currentFrom = $this->readFrom($qb);
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
     * Resolves expression values through the grammar before extracting an alias.
     */
    private function getFromAliasOrTable(QueryBuilder $qb): string
    {
        $from = $this->readFrom($qb);

        if ($from === '') {
            return '';
        }

        if (preg_match('/\s+as\s+(\S+)$/i', $from, $m)) {
            return strtolower($m[1]);
        }

        return strtolower($from);
    }

    /**
     * Resolves the FROM text using Illuminate's expression contract.
     */
    private function readFrom(QueryBuilder $qb): string
    {
        $from = $qb->from;
        if ($from instanceof Expression) {
            return (string) $from->getValue($qb->getGrammar());
        }

        return (string) $from;
    }

    /**
     * Checks if a table reference is already joined in the query builder.
     */
    private function hasJoin(QueryBuilder $qb, string $tableRef): bool
    {
        $joins = $qb->joins;
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
