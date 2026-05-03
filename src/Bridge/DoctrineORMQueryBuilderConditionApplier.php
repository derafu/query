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
use Derafu\Query\Bridge\Exception\UnsupportedOperatorException;
use Derafu\Query\Filter\CompositeCondition;
use Derafu\Query\Filter\Condition;
use Derafu\Query\Filter\Contract\CompositeConditionInterface;
use Derafu\Query\Filter\Contract\ConditionInterface;
use Derafu\Query\Filter\Path;
use Derafu\Query\Filter\Segment;
use Doctrine\ORM\QueryBuilder as DoctrineORMQueryBuilder;

/**
 * Applies parsed conditions to a Doctrine ORM QueryBuilder.
 *
 * Generates DQL-compatible WHERE/HAVING clauses via SqlBuilderWhere, using the
 * 'pgsql' driver which produces standard SQL compatible with DQL for all
 * supported operators.
 *
 * Operators that require SQL-specific functions (DATE, MONTH, YEAR, PERIOD,
 * bitwise, regexp, ILIKE) are not expressible in DQL and will throw
 * UnsupportedOperatorException.
 *
 * Single-segment paths (e.g. "status?=active") are automatically qualified
 * with the root entity alias from the QueryBuilder's FROM clause, so the
 * generated DQL becomes "c.status = :param" rather than the bare "status = :param"
 * which is invalid DQL. Multi-segment paths (e.g. "invoices[alias:i]__status")
 * are left unchanged, allowing explicit alias control for JOINs.
 *
 * The "on:" option in path segments is ignored — Doctrine resolves join
 * conditions from the entity association mapping.
 */
final class DoctrineORMQueryBuilderConditionApplier implements QueryBuilderConditionApplierInterface
{
    use ConditionApplierTrait;

    private const UNSUPPORTED_TYPES = ['date', 'binary', 'regexp'];

    private const UNSUPPORTED_SYMBOLS = ['ilike:', 'notilike:'];

    /**
     * {@inheritDoc}
     */
    public function apply(
        object $queryBuilder,
        ConditionInterface|CompositeConditionInterface $condition
    ): void {
        assert($queryBuilder instanceof DoctrineORMQueryBuilder);

        $this->assertDqlCompatible($condition);
        $this->addOrmJoins($queryBuilder, $condition);

        $rootAlias = $this->getRootAlias($queryBuilder);
        if ($rootAlias !== '') {
            $condition = $this->qualifyPaths($condition, $rootAlias);
        }

        ['sql' => $sql, 'parameters' => $params] = $this->buildConditionSql('pgsql', $condition);

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
        assert($queryBuilder instanceof DoctrineORMQueryBuilder);

        $this->assertDqlCompatible($condition);

        $rootAlias = $this->getRootAlias($queryBuilder);
        if ($rootAlias !== '') {
            $condition = $this->qualifyPaths($condition, $rootAlias);
        }

        ['sql' => $sql, 'parameters' => $params] = $this->buildConditionSql('pgsql', $condition);

        $queryBuilder->andHaving($sql);
        foreach ($params as $name => $value) {
            $queryBuilder->setParameter($name, $value);
        }
    }

    /**
     * Walks the condition tree and throws for any DQL-incompatible operator.
     */
    private function assertDqlCompatible(
        ConditionInterface|CompositeConditionInterface $condition
    ): void {
        if ($condition instanceof CompositeConditionInterface) {
            foreach ($condition->getConditions() as $sub) {
                $this->assertDqlCompatible($sub);
            }
            return;
        }

        $operator = $condition->getFilter()->getOperator();
        $effective = $operator->getBaseOperator() ?? $operator;

        if (
            in_array($effective->getType(), self::UNSUPPORTED_TYPES, true)
            || in_array($effective->getSymbol(), self::UNSUPPORTED_SYMBOLS, true)
        ) {
            throw UnsupportedOperatorException::forOperator($operator->getSymbol());
        }
    }

    /**
     * Rewrites single-segment conditions to use the root entity alias as prefix.
     *
     * A path like "status" (1 segment) becomes "_root[alias:c]__status" (2 segments),
     * which makes SqlBuilderWhere generate "c.status" instead of bare "status".
     * Paths that already have 2+ segments are left unchanged.
     */
    private function qualifyPaths(
        ConditionInterface|CompositeConditionInterface $condition,
        string $rootAlias
    ): ConditionInterface|CompositeConditionInterface {
        if ($condition instanceof CompositeConditionInterface) {
            $newComposite = new CompositeCondition($condition->getType());
            foreach ($condition->getConditions() as $sub) {
                $newComposite->add($this->qualifyPaths($sub, $rootAlias));
            }
            return $newComposite;
        }

        $segments = $condition->getPath()->getSegments();

        if (count($segments) !== 1) {
            return $condition;
        }

        $rootSegment = new Segment($rootAlias, ['alias' => $rootAlias]);
        $newPath = new Path([$rootSegment, ...$segments]);

        return new Condition($newPath, $condition->getFilter(), $condition->isLiteral());
    }

    /**
     * Returns the alias of the first FROM entity, or empty string if FROM is unset.
     */
    private function getRootAlias(DoctrineORMQueryBuilder $qb): string
    {
        $from = $qb->getDQLPart('from');

        if (empty($from)) {
            return '';
        }

        return $from[0]->getAlias();
    }

    /**
     * Extracts ORM join specs from condition paths and adds missing joins to the QB.
     */
    private function addOrmJoins(
        DoctrineORMQueryBuilder $qb,
        ConditionInterface|CompositeConditionInterface $condition
    ): void {
        $paths = $this->extractPaths($condition);

        foreach ($this->buildOrmJoinSpecsFromPaths($paths) as $spec) {
            if (!$this->hasOrmJoin($qb, $spec['alias'])) {
                match ($spec['type']) {
                    'left' => $qb->leftJoin($spec['join'], $spec['alias']),
                    default => $qb->innerJoin($spec['join'], $spec['alias']),
                };
            }
        }
    }

    /**
     * Checks if an alias is already joined in the ORM QueryBuilder.
     */
    private function hasOrmJoin(DoctrineORMQueryBuilder $qb, string $alias): bool
    {
        $joins = $qb->getDQLPart('join');

        foreach ($joins as $joinList) {
            foreach ($joinList as $join) {
                if (strtolower($join->getAlias() ?? '') === strtolower($alias)) {
                    return true;
                }
            }
        }

        return false;
    }
}
