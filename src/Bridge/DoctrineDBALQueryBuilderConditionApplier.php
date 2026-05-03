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
use Derafu\Query\Filter\Contract\CompositeConditionInterface;
use Derafu\Query\Filter\Contract\ConditionInterface;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Query\QueryBuilder as DoctrineDBALQueryBuilder;
use ReflectionProperty;

/**
 * Applies parsed conditions to a Doctrine DBAL QueryBuilder.
 *
 * Handles WHERE clause generation, FROM inference from path segments,
 * and JOIN application (with deduplication). Uses SqlBuilderWhere
 * internally to generate driver-specific SQL fragments.
 *
 * FROM and JOIN state are read via reflection because the Doctrine DBAL
 * QueryBuilder exposes no public getters for those internal arrays.
 */
final class DoctrineDBALQueryBuilderConditionApplier implements QueryBuilderConditionApplierInterface
{
    use ConditionApplierTrait;

    /**
     * {@inheritDoc}
     */
    public function apply(
        object $queryBuilder,
        ConditionInterface|CompositeConditionInterface $condition
    ): void {
        assert($queryBuilder instanceof DoctrineDBALQueryBuilder);

        $this->processFromAndJoins($queryBuilder, $condition);

        ['sql' => $sql, 'parameters' => $params] = $this->buildConditionSql(
            $this->resolveDriver($queryBuilder),
            $condition
        );

        $queryBuilder->andWhere($sql);
        foreach ($params as $name => $value) {
            $queryBuilder->setParameter($name, $value);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function applyHaving(
        object $queryBuilder,
        ConditionInterface|CompositeConditionInterface $condition
    ): void {
        assert($queryBuilder instanceof DoctrineDBALQueryBuilder);

        ['sql' => $sql, 'parameters' => $params] = $this->buildConditionSql(
            $this->resolveDriver($queryBuilder),
            $condition
        );

        $queryBuilder->andHaving($sql);
        foreach ($params as $name => $value) {
            $queryBuilder->setParameter($name, $value);
        }
    }

    /**
     * Maps the Doctrine DBAL platform to the driver name used by SqlBuilderWhere.
     */
    private function resolveDriver(DoctrineDBALQueryBuilder $qb): string
    {
        $connection = (new ReflectionProperty($qb, 'connection'))->getValue($qb);
        assert($connection instanceof \Doctrine\DBAL\Connection);
        $platform = $connection->getDatabasePlatform();

        return match (true) {
            $platform instanceof MySQLPlatform => 'mysql',
            $platform instanceof PostgreSQLPlatform => 'pgsql',
            $platform instanceof SQLitePlatform => 'sqlite',
            $platform instanceof SQLServerPlatform => 'sqlsrv',
            $platform instanceof OraclePlatform => 'oci',
            default => 'pgsql',
        };
    }

    /**
     * Infers the base table from path segments (if FROM not yet set)
     * and applies all JOIN clauses found in the condition paths.
     */
    private function processFromAndJoins(
        DoctrineDBALQueryBuilder $qb,
        ConditionInterface|CompositeConditionInterface $condition
    ): void {
        $paths = $this->extractPaths($condition);

        $fromTable = $this->getFrom($qb);

        if (empty($fromTable)) {
            $fromSpec = $this->inferFromTable($paths);
            if ($fromSpec !== null) {
                $qb->from($fromSpec['table'], $fromSpec['alias']);
                $fromTable = strtolower($fromSpec['table']);
            }
        }

        foreach ($this->buildJoinSpecsFromPaths($paths, $fromTable) as $spec) {
            if (!$this->hasJoin($qb, $spec['alias'])) {
                match ($spec['type']) {
                    'left' => $qb->leftJoin(
                        $spec['fromAlias'],
                        $spec['table'],
                        $spec['alias'],
                        $spec['condition']
                    ),
                    'right' => $qb->rightJoin(
                        $spec['fromAlias'],
                        $spec['table'],
                        $spec['alias'],
                        $spec['condition']
                    ),
                    default => $qb->innerJoin(
                        $spec['fromAlias'],
                        $spec['table'],
                        $spec['alias'],
                        $spec['condition']
                    ),
                };
            }
        }
    }

    /**
     * Returns the lower-cased alias (or table name) of the first FROM entry,
     * or an empty string when no FROM has been set yet.
     *
     * Doctrine DBAL stores FROM entries in a private array, so reflection is
     * required to inspect it without resorting to getSQL() parsing.
     */
    private function getFrom(DoctrineDBALQueryBuilder $qb): string
    {
        $from = (new ReflectionProperty($qb, 'from'))->getValue($qb);

        if (empty($from)) {
            return '';
        }

        return strtolower($from[0]->table);
    }

    /**
     * Checks if a JOIN with the given alias is already present.
     *
     * Doctrine DBAL stores joins in a private array indexed by from-alias,
     * so reflection is required here as well.
     */
    private function hasJoin(DoctrineDBALQueryBuilder $qb, string $alias): bool
    {
        $joins = (new ReflectionProperty($qb, 'join'))->getValue($qb);

        foreach ($joins as $fromJoins) {
            foreach ($fromJoins as $join) {
                if (strtolower($join->alias) === strtolower($alias)) {
                    return true;
                }
            }
        }

        return false;
    }
}
