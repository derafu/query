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
use Derafu\Query\Filter\Contract\SegmentInterface;
use Derafu\Query\Filter\Path;
use Derafu\Query\Filter\Segment;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\ORM\Mapping\InverseSideMapping;
use Doctrine\ORM\QueryBuilder as DoctrineORMQueryBuilder;

/**
 * Applies parsed conditions to a Doctrine ORM QueryBuilder.
 *
 * Generates DQL-compatible WHERE/HAVING clauses via SqlBuilderWhere, using the
 * actual database platform resolved from the EntityManager connection.
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

        ['sql' => $sql, 'parameters' => $params] = $this->buildDql(
            $queryBuilder,
            $condition,
            $rootAlias
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
        assert($queryBuilder instanceof DoctrineORMQueryBuilder);

        $this->assertDqlCompatible($condition);

        $rootAlias = $this->getRootAlias($queryBuilder);
        if ($rootAlias !== '') {
            $condition = $this->qualifyPaths($condition, $rootAlias);
        }

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
    private function resolveDriver(DoctrineORMQueryBuilder $qb): string
    {
        $platform = $qb->getEntityManager()->getConnection()->getDatabasePlatform();

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
     * Recursively builds DQL for a condition tree, dispatching EXISTS paths to
     * buildExistsDql() and regular conditions through qualifyPaths() + SQL builder.
     *
     * @return array{sql: string, parameters: array<string, mixed>}
     */
    private function buildDql(
        DoctrineORMQueryBuilder $qb,
        ConditionInterface|CompositeConditionInterface $condition,
        string $rootAlias
    ): array {
        if ($condition instanceof CompositeConditionInterface) {
            $parts = [];
            $parameters = [];
            foreach ($condition->getConditions() as $sub) {
                $result = $this->buildDql($qb, $sub, $rootAlias);
                $parts[] = $result['sql'];
                $parameters = array_merge($parameters, $result['parameters']);
            }
            $sql = '(' . implode(' ' . $condition->getType() . ' ', $parts) . ')';
            return ['sql' => $sql, 'parameters' => $parameters];
        }

        if ($condition->getPath()->getFirstSegment()->getOption('subquery') === 'exists') {
            return $this->buildExistsDql($qb, $condition, $rootAlias);
        }

        if ($rootAlias !== '') {
            $condition = $this->qualifyPaths($condition, $rootAlias);
        }

        return $this->buildConditionSql($this->resolveDriver($qb), $condition);
    }

    /**
     * Generates DQL for an EXISTS-path condition (___assoc syntax).
     *
     * Simple emptiness (is:empty / isnot:empty) → DQL SIZE() comparison.
     * Column filter → correlated EXISTS() DQL subquery via entity metadata.
     *
     * @return array{sql: string, parameters: array<string, mixed>}
     */
    private function buildExistsDql(
        DoctrineORMQueryBuilder $qb,
        ConditionInterface $condition,
        string $rootAlias
    ): array {
        $segments  = $condition->getPath()->getSegments();
        $filter    = $condition->getFilter();
        $operator  = $filter->getOperator();
        $baseOp    = $operator->getBaseOperator();
        $effective = $baseOp ?? $operator;

        $existsSegments = [];
        $columnSegment  = null;
        foreach ($segments as $segment) {
            if ($segment->getOption('subquery') === 'exists') {
                $existsSegments[] = $segment;
            } else {
                $columnSegment = $segment;
            }
        }

        $assocName = $existsSegments[0]->getName();

        if ($columnSegment === null) {
            $isEmpty   = ($effective->getSymbol() === 'is:empty');
            $comparison = $isEmpty ? '= 0' : '> 0';
            return [
                'sql'        => 'SIZE(' . $rootAlias . '.' . $assocName . ') ' . $comparison,
                'parameters' => [],
            ];
        }

        // Column filter: correlated EXISTS subquery using entity metadata.
        $em              = $qb->getEntityManager();
        $rootEntityClass = $this->getRootEntityClass($qb);
        $meta            = $em->getClassMetadata($rootEntityClass);
        $assocMapping    = $meta->getAssociationMapping($assocName);
        $targetClass     = $assocMapping->targetEntity;
        $inversedField   = $assocMapping instanceof InverseSideMapping
            ? $assocMapping->backRefFieldName()
            : null;

        // Aggregate scalar subquery: ___assoc__AGG(col)?op:value
        if (preg_match('/^(SUM|AVG|COUNT|MIN|MAX)\s*\((.+)\)$/i', $columnSegment->getName(), $m)) {
            return $this->buildAggregateScalarDql(
                $qb,
                $condition,
                $rootAlias,
                $existsSegments[0],
                $targetClass,
                $inversedField,
                strtoupper($m[1]),
                trim($m[2])
            );
        }

        $subAlias = '_' . $assocName . '0';

        // Build the column filter using SqlBuilderWhere via a 2-segment path.
        $prefixSegment    = new Segment($subAlias, ['alias' => $subAlias]);
        $qualifiedPath    = new Path([$prefixSegment, $columnSegment]);
        $qualifiedCondition = new Condition($qualifiedPath, $filter, $condition->isLiteral());

        ['sql' => $filterSql, 'parameters' => $filterParams] = $this->buildConditionSql(
            $this->resolveDriver($qb),
            $qualifiedCondition
        );

        $whereParts = [];
        if ($inversedField !== null) {
            $whereParts[] = $subAlias . '.' . $inversedField . ' = ' . $rootAlias;
        }
        $whereParts[] = $filterSql;

        $dql = 'EXISTS(SELECT ' . $subAlias . '.id FROM ' . $targetClass . ' ' . $subAlias
            . ' WHERE ' . implode(' AND ', $whereParts) . ')';

        return ['sql' => $dql, 'parameters' => $filterParams];
    }

    /**
     * Generates DQL for an aggregate scalar subquery condition (___assoc__AGG(col)?op).
     *
     * Produces: (SELECT AGG(alias.prop) FROM EntityClass alias WHERE alias.rel = rootAlias) op :param
     *
     * COUNT(*) is not valid DQL; it is rewritten to COUNT(alias.primaryKeyField).
     *
     * @return array{sql: string, parameters: array<string, mixed>}
     */
    private function buildAggregateScalarDql(
        DoctrineORMQueryBuilder $qb,
        ConditionInterface $condition,
        string $rootAlias,
        SegmentInterface $primarySegment,
        string $targetClass,
        ?string $inversedField,
        string $funcName,
        string $funcArg
    ): array {
        $subAlias = $this->sanitizeSqlSimpleIdentifier(
            $primarySegment->getOption('alias') ?? strtolower($primarySegment->getName()[0])
        );

        // DQL aggregate expression; COUNT(*) must become COUNT(alias.pk).
        if ($funcArg === '*') {
            // COUNT(*) = 0 / > 0 → rewrite to DQL SIZE() (native DQL, same optimizer benefit).
            $filter    = $condition->getFilter();
            $operator  = $filter->getOperator();
            $effective = $operator->getBaseOperator() ?? $operator;
            $sizeComp  = $this->resolveCountStarToSizeComparison($effective, $filter->getValue());
            if ($sizeComp !== null) {
                $assocName = $this->sanitizeSqlSimpleIdentifier($primarySegment->getName());
                return [
                    'sql'        => 'SIZE(' . $rootAlias . '.' . $assocName . ') ' . $sizeComp,
                    'parameters' => [],
                ];
            }

            $em       = $qb->getEntityManager();
            $pkFields = $em->getClassMetadata($targetClass)->getIdentifierFieldNames();
            $pkField  = $this->sanitizeSqlSimpleIdentifier($pkFields[0] ?? 'id');
            $aggExpr  = $funcName . '(' . $subAlias . '.' . $pkField . ')';
        } else {
            $aggExpr = $funcName . '(' . $subAlias . '.' . $this->sanitizeSqlSimpleIdentifier($funcArg) . ')';
        }

        // Correlation condition using the DQL association field (not the SQL column).
        $whereParts = [];
        if ($inversedField !== null) {
            $whereParts[] = $subAlias . '.' . $inversedField . ' = ' . $rootAlias;
        }

        $dqlSubquery = 'SELECT ' . $aggExpr . ' FROM ' . $targetClass . ' ' . $subAlias;
        if (!empty($whereParts)) {
            $dqlSubquery .= ' WHERE ' . implode(' AND ', $whereParts);
        }

        // Use a DERAFUAGG placeholder to generate the comparison DQL fragment
        // via buildConditionSql(), then replace the placeholder with the subquery.
        $placeholderSegment   = new Segment('DERAFUAGG', []);
        $placeholderPath      = new Path([$placeholderSegment]);
        $placeholderCondition = new Condition(
            $placeholderPath,
            $condition->getFilter(),
            $condition->isLiteral()
        );

        ['sql' => $compSql, 'parameters' => $compParams] = $this->buildConditionSql(
            $this->resolveDriver($qb),
            $placeholderCondition
        );

        $finalSql = preg_replace('/\bDERAFUAGG\b/', '(' . $dqlSubquery . ')', $compSql, 1);

        return ['sql' => $finalSql, 'parameters' => $compParams];
    }

    /**
     * Maps COUNT(*) existence conditions to a DQL SIZE() comparison string.
     *
     * Returns the comparison suffix ('= 0', '> 0', etc.) when the operator and
     * value form a pure existence check, or null when not rewritable.
     */
    private function resolveCountStarToSizeComparison(object $effective, mixed $value): ?string
    {
        $symbol = $effective->getSymbol();
        $v      = trim((string)($value ?? ''));

        return match (true) {
            $symbol === 'is:empty'                   => '= 0',
            $symbol === 'isnot:empty'                => '> 0',
            $symbol === '='  && $v === '0'           => '= 0',
            $symbol === '!=' && $v === '0'           => '> 0',
            $symbol === '>'  && $v === '0'           => '> 0',
            $symbol === '>=' && $v === '1'           => '> 0',
            $symbol === '<'  && $v === '1'           => '= 0',
            $symbol === '<=' && $v === '0'           => '= 0',
            default                                  => null,
        };
    }

    /**
     * Returns the entity class name of the first FROM entry in the DQL query.
     */
    private function getRootEntityClass(DoctrineORMQueryBuilder $qb): string
    {
        $from = $qb->getDQLPart('from');

        if (empty($from)) {
            return '';
        }

        return $from[0]->getFrom();
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
