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
use Derafu\Query\Bridge\DoctrineORM\Dql\MySqlBinaryStringFunction;
use Derafu\Query\Bridge\DoctrineORM\NumericComparisonType;
use Derafu\Query\Bridge\Exception\UnsupportedOperatorException;
use Derafu\Query\Builder\AliasScope;
use Derafu\Query\Filter\CompositeCondition;
use Derafu\Query\Filter\Condition;
use Derafu\Query\Filter\Contract\CompositeConditionInterface;
use Derafu\Query\Filter\Contract\ConditionInterface;
use Derafu\Query\Filter\Contract\SegmentInterface;
use Derafu\Query\Filter\Path;
use Derafu\Query\Filter\Segment;
use Derafu\Query\Operator\Contract\OperatorInterface;
use Derafu\Translation\Exception\Core\TranslatableRuntimeException as RuntimeException;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\TokenType;
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
        $this->configureDqlFunctions($queryBuilder);
        $this->addOrmJoins($queryBuilder, $condition);

        $rootAlias = $this->getRootAlias($queryBuilder);

        $compiled = $this->buildDql(
            $queryBuilder,
            $condition,
            $rootAlias,
            $this->createDqlAliasScope($queryBuilder, $condition)
        );

        $queryBuilder->andWhere($compiled['sql']);
        foreach ($compiled['parameters'] as $name => $value) {
            $type = $compiled['types'][$name] ?? null;
            if ($type === NumericComparisonType::class && !Type::hasType($type)) {
                Type::addType($type, NumericComparisonType::class);
            }
            $queryBuilder->setParameter($name, $value, $type);
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
        $this->configureDqlFunctions($queryBuilder);

        $rootAlias = $this->getRootAlias($queryBuilder);
        if ($rootAlias !== '') {
            $condition = $this->qualifyPaths($condition, $rootAlias);
        }

        ['sql' => $sql, 'parameters' => $params] = $this->buildConditionSql(
            $this->resolveDriver($queryBuilder),
            $condition,
            dql: true
        );

        $queryBuilder->andHaving($sql);
        foreach ($params as $name => $value) {
            $queryBuilder->setParameter($name, $value);
        }
    }

    /**
     * Registers MySQL-specific DQL support without consumer configuration.
     */
    private function configureDqlFunctions(DoctrineORMQueryBuilder $qb): void
    {
        if ($this->resolveDriver($qb) === 'mysql') {
            $qb->getEntityManager()->getConfiguration()->addCustomStringFunction(
                'DERAFU_MYSQL_BINARY_STRING',
                MySqlBinaryStringFunction::class
            );
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
     * @return array{sql: string, parameters: array<string, mixed>, types?: array<string, string>}
     */
    private function buildDql(
        DoctrineORMQueryBuilder $qb,
        ConditionInterface|CompositeConditionInterface $condition,
        string $rootAlias,
        AliasScope $aliasScope
    ): array {
        if ($condition instanceof CompositeConditionInterface) {
            $parts = [];
            $parameters = [];
            $types = [];
            foreach ($condition->getConditions() as $sub) {
                $result = $this->buildDql($qb, $sub, $rootAlias, $aliasScope);
                $parts[] = $result['sql'];
                $parameters = array_merge($parameters, $result['parameters']);
                $types = array_merge($types, $result['types'] ?? []);
            }
            $sql = '(' . implode(' ' . $condition->getType() . ' ', $parts) . ')';
            return ['sql' => $sql, 'parameters' => $parameters, 'types' => $types];
        }

        if ($this->readSegmentOptions($condition->getPath()->getFirstSegment())->string('subquery') === 'exists') {
            return $this->buildExistsDql($qb, $condition, $rootAlias, $aliasScope);
        }

        if ($rootAlias !== '') {
            $condition = $this->qualifyPaths($condition, $rootAlias);
        }

        return $this->buildConditionSql($this->resolveDriver($qb), $condition, dql: true);
    }

    /**
     * Generates DQL for an EXISTS-path condition (___assoc syntax).
     *
     * Collection emptiness uses SIZE(); single-valued associations use EXISTS().
     * Column filter → correlated EXISTS() DQL subquery via entity metadata.
     *
     * @return array{sql: string, parameters: array<string, mixed>, types?: array<string, string>}
     */
    private function buildExistsDql(
        DoctrineORMQueryBuilder $qb,
        ConditionInterface $condition,
        string $rootAlias,
        AliasScope $aliasScope
    ): array {
        $segments  = $condition->getPath()->getSegments();
        $filter    = $condition->getFilter();
        $operator  = $filter->getOperator();
        $baseOp    = $operator->getBaseOperator();
        $effective = $baseOp ?? $operator;

        $existsSegments = [];
        $columnSegment  = null;
        foreach ($segments as $segment) {
            if ($this->readSegmentOptions($segment)->string('subquery') === 'exists') {
                $existsSegments[] = $segment;
            } else {
                $columnSegment = $segment;
            }
        }

        $assocName = $existsSegments[0]->getName();

        $meta = $qb->getEntityManager()->getClassMetadata($this->getRootEntityClass($qb));
        $assocMapping = $meta->getAssociationMapping($assocName);

        if ($columnSegment === null) {
            $isEmpty = ($effective->getSymbol() === 'is:empty');
            if ($assocMapping->isToMany() && count($existsSegments) === 1) {
                return [
                    'sql' => 'SIZE(' . $rootAlias . '.' . $assocName . ') ' . ($isEmpty ? '= 0' : '> 0'),
                    'parameters' => [],
                ];
            }

            $subquery = $this->buildAssociationSubquery($qb, $rootAlias, $existsSegments, $this->resolveAssociationAliases($qb, $existsSegments, $aliasScope));
            return [
                'sql' => ($isEmpty ? 'NOT ' : '') . 'EXISTS(SELECT 1 ' . $subquery . ')',
                'parameters' => [],
            ];
        }

        // Aggregate scalar subquery: ___assoc__AGG(col)?op:value
        if (preg_match('/^(SUM|AVG|COUNT|MIN|MAX)\s*\((.+)\)$/i', $columnSegment->getName(), $m)) {
            return $this->buildAggregateScalarDql(
                $qb,
                $condition,
                $rootAlias,
                $existsSegments[0],
                $existsSegments,
                $assocMapping->isToMany() && count($existsSegments) === 1,
                strtoupper($m[1]),
                trim($m[2]),
                $aliasScope
            );
        }

        $aliases = $this->resolveAssociationAliases($qb, $existsSegments, $aliasScope);
        $subAlias = $aliases['targets'][count($aliases['targets']) - 1];

        // Build the column filter using SqlBuilderWhere via a 2-segment path.
        $prefixSegment    = new Segment($subAlias, ['alias' => $subAlias]);
        $qualifiedPath    = new Path([$prefixSegment, $columnSegment]);
        $qualifiedCondition = new Condition($qualifiedPath, $filter, $condition->isLiteral());

        ['sql' => $filterSql, 'parameters' => $filterParams] = $this->buildConditionSql(
            $this->resolveDriver($qb),
            $qualifiedCondition,
            dql: true
        );

        $subquery = $this->buildAssociationSubquery($qb, $rootAlias, $existsSegments, $aliases);
        $dql = 'EXISTS(SELECT 1 ' . $subquery . ' AND (' . $filterSql . '))';

        return ['sql' => $dql, 'parameters' => $filterParams];
    }

    /**
     * Generates DQL for an aggregate scalar subquery condition (___assoc__AGG(col)?op).
     *
     * Aggregates use the same mapped association traversal as ordinary EXISTS filters.
     *
     * COUNT(*) is not valid DQL; it is rewritten to COUNT(alias).
     * Comparisons use EXISTS with HAVING so scalar, list, range and null
     * operators share a DQL-compatible aggregate expression.
     *
     * @param list<SegmentInterface> $associationSegments
     * @return array{sql: string, parameters: array<string, mixed>, types?: array<string, string>}
     */
    private function buildAggregateScalarDql(
        DoctrineORMQueryBuilder $qb,
        ConditionInterface $condition,
        string $rootAlias,
        SegmentInterface $primarySegment,
        array $associationSegments,
        bool $canUseSize,
        string $funcName,
        string $funcArg,
        AliasScope $aliasScope
    ): array {
        // DQL aggregate expression; COUNT(*) must become COUNT(alias).
        if ($funcArg === '*') {
            // COUNT(*) = 0 / > 0 → rewrite to DQL SIZE() (native DQL, same optimizer benefit).
            $filter    = $condition->getFilter();
            $operator  = $filter->getOperator();
            $effective = $operator->getBaseOperator() ?? $operator;
            $sizeComp  = $this->resolveCountStarToSizeComparison($effective, $filter->getValue());
            if ($sizeComp !== null && $canUseSize) {
                $assocName = $this->sanitizeSqlSimpleIdentifier($primarySegment->getName());
                return [
                    'sql'        => 'SIZE(' . $rootAlias . '.' . $assocName . ') ' . $sizeComp,
                    'parameters' => [],
                ];
            }

        }

        $aliases = $this->resolveAssociationAliases($qb, $associationSegments, $aliasScope);
        $subAlias = $aliases['targets'][count($aliases['targets']) - 1];
        $aggExpr = $funcName . '(' . ($funcArg === '*'
            ? $subAlias
            : $subAlias . '.' . $this->sanitizeSqlSimpleIdentifier($funcArg)) . ')';

        $dqlSubquery = 'SELECT ' . $aggExpr . ' '
            . $this->buildAssociationSubquery($qb, $rootAlias, $associationSegments, $aliases);

        // Use a DERAFUAGG placeholder to generate the comparison DQL fragment
        // via buildConditionSql(), then replace the placeholder with the aggregate.
        $placeholderSegment   = new Segment('DERAFUAGG', []);
        $placeholderPath      = new Path([$placeholderSegment]);
        $placeholderCondition = new Condition(
            $placeholderPath,
            $condition->getFilter(),
            $condition->isLiteral()
        );

        ['sql' => $compSql, 'parameters' => $compParams] = $this->buildConditionSql(
            $this->resolveDriver($qb),
            $placeholderCondition,
            dql: true
        );

        $resultType = $this->resolveAggregateResultType($qb, $associationSegments, $funcName, $funcArg);
        $operator = $condition->getFilter()->getOperator();
        $effective = $operator->getBaseOperator() ?? $operator;
        $types = [];
        if (in_array($effective->getType(), ['standard', 'list', 'range'], true)
            && in_array($resultType, [Types::SMALLINT, Types::INTEGER, Types::BIGINT, Types::DECIMAL, Types::NUMBER, Types::SMALLFLOAT, Types::FLOAT], true)
        ) {
            foreach ($compParams as $name => $value) {
                if ($value === null) {
                    continue;
                }
                // Validate each scalar/list/range operand before SQLite's CAST,
                // which would otherwise silently turn invalid text into zero.
                if (!is_string($value) || !is_numeric($value)) {
                    throw new InvalidArgumentException([
                        'Invalid value format for operator {operator}: {value}.',
                        'operator' => $operator->getSymbol(),
                        'value' => $condition->getFilter()->getValue() ?? '',
                    ]);
                }
                $types[$name] = NumericComparisonType::class;
            }
        }

        $finalSql = preg_replace('/\bDERAFUAGG\b/', $aggExpr, $compSql, 1);
        if ($finalSql === null) {
            throw new RuntimeException([
                'Failed to substitute aggregate DQL subquery: {error}',
                'error' => preg_last_error_msg(),
            ]);
        }

        return [
            'sql' => 'EXISTS (' . $dqlSubquery . ' HAVING ' . $finalSql . ')',
            'parameters' => $compParams,
            'types' => $types,
        ];
    }

    /**
     * Resolves aggregate semantics from the complete association path and the
     * mapped field. MIN/MAX preserve text/date types; numeric thresholds retain
     * their decimal representation even when COUNT itself returns an integer.
     *
     * @param list<SegmentInterface> $segments
     */
    private function resolveAggregateResultType(
        DoctrineORMQueryBuilder $qb,
        array $segments,
        string $function,
        string $field
    ): string {
        if ($function === 'COUNT') {
            return Types::BIGINT;
        }

        $em = $qb->getEntityManager();
        $metadata = $em->getClassMetadata($this->getRootEntityClass($qb));
        foreach ($segments as $segment) {
            $metadata = $em->getClassMetadata($metadata->getAssociationMapping($segment->getName())->targetEntity);
        }
        $fieldType = $metadata->getTypeOfField($field) ?? Types::STRING;
        if ($function === 'AVG') {
            return in_array($fieldType, [Types::DECIMAL, Types::NUMBER], true) ? $fieldType : Types::FLOAT;
        }

        return $fieldType;
    }

    /**
     * Doctrine requires automatic aliases to be unique across the entire DQL
     * query. Rebuild the allocation namespace from the current query on each
     * apply(), so repeated calls, cloned builders and different applier instances
     * cannot reset it to names that already exist. The lexer excludes literals
     * and parameters. Reserving identifier tokens is conservative for generated
     * names only; explicit aliases are validated in their local scope.
     */
    private function createDqlAliasScope(
        DoctrineORMQueryBuilder $qb,
        ConditionInterface|CompositeConditionInterface $condition
    ): AliasScope {
        $reserved = $qb->getAllAliases();
        $lexer = new Lexer($qb->getDQL());
        while ($lexer->moveNext()) {
            $token = $lexer->lookahead;
            if ($token->type === TokenType::T_IDENTIFIER) {
                $reserved[] = $token->value;
            }
        }

        // Reserve explicit names from the complete incoming condition tree
        // before allocating any helper alias in any of its subqueries.
        foreach ($this->extractPaths($condition) as $path) {
            foreach ($path->getSegments() as $segment) {
                $explicit = $this->readSegmentOptions($segment)->string('alias');
                if ($explicit !== null) {
                    $reserved[] = $this->sanitizeSqlSimpleIdentifier($explicit);
                }
            }
        }

        return new AliasScope($reserved);
    }

    /**
     * @param list<SegmentInterface> $segments
     * @return array{source: string, targets: list<string>}
     */
    private function resolveAssociationAliases(DoctrineORMQueryBuilder $qb, array $segments, AliasScope $allocator): array
    {
        $scope = new AliasScope($qb->getAllAliases());
        foreach ($segments as $segment) {
            $explicit = $this->readSegmentOptions($segment)->string('alias');
            if ($explicit !== null) {
                $scope->reserve($this->sanitizeSqlSimpleIdentifier($explicit));
            }
        }
        $source = $allocator->generate();
        $targets = [];
        foreach ($segments as $segment) {
            $explicit = $this->readSegmentOptions($segment)->string('alias');
            $targets[] = $explicit !== null ? $this->sanitizeSqlSimpleIdentifier($explicit) : $allocator->generate();
        }

        return ['source' => $source, 'targets' => $targets];
    }

    /**
     * Traverses mapped associations from a correlated copy of the root entity.
     * Doctrine resolves owning sides and join tables; no inverse property or
     * conventional identifier name is required.
     *
     * @param list<SegmentInterface> $segments
     * @param array{source: string, targets: list<string>} $aliases
     */
    private function buildAssociationSubquery(
        DoctrineORMQueryBuilder $qb,
        string $rootAlias,
        array $segments,
        array $aliases
    ): string {
        $rootClass = $this->getRootEntityClass($qb);
        $metadata = $qb->getEntityManager()->getClassMetadata($rootClass);
        $sourceAlias = $aliases['source'];
        $from = 'FROM ' . $rootClass . ' ' . $sourceAlias;
        $parentAlias = $sourceAlias;
        foreach ($segments as $key => $segment) {
            $alias = $aliases['targets'][$key];
            $from .= ' JOIN ' . $parentAlias . '.' . $this->sanitizeSqlSimpleIdentifier($segment->getName()) . ' ' . $alias;
            $parentAlias = $alias;
        }

        $correlation = [];
        foreach ($metadata->getIdentifierFieldNames() as $field) {
            $inner = $sourceAlias . '.' . $field;
            $outer = $rootAlias . '.' . $field;
            if ($metadata->hasAssociation($field)) {
                $inner = 'IDENTITY(' . $inner . ')';
                $outer = 'IDENTITY(' . $outer . ')';
            }
            $correlation[] = $inner . ' = ' . $outer;
        }

        return $from . ' WHERE ' . implode(' AND ', $correlation);
    }

    /**
     * Maps COUNT(*) existence conditions to a DQL SIZE() comparison string.
     *
     * Returns the comparison suffix ('= 0', '> 0', etc.) when the operator and
     * value form a pure existence check, or null when not rewritable.
     *
     * @param OperatorInterface $effective
     */
    private function resolveCountStarToSizeComparison(object $effective, ?string $value): ?string
    {
        $symbol = $effective->getSymbol();
        $v      = trim($value ?? '');

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
     *
     * @return class-string
     */
    private function getRootEntityClass(DoctrineORMQueryBuilder $qb): string
    {
        $entityClass = $qb->getRootEntities()[0] ?? null;
        if ($entityClass === null) {
            throw new RuntimeException('A correlated DQL subquery requires a root entity in the FROM clause.');
        }

        return $entityClass;
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
        return $qb->getRootAliases()[0] ?? '';
    }

    /**
     * Extracts ORM join specs from condition paths and adds missing joins to the QB.
     */
    private function addOrmJoins(
        DoctrineORMQueryBuilder $qb,
        ConditionInterface|CompositeConditionInterface $condition
    ): void {
        $paths = [];
        foreach ($this->extractPaths($condition) as $path) {
            // EXISTS paths build their complete association traversal inside
            // the correlated subquery, not in the outer query's JOIN scope.
            if ($this->readSegmentOptions($path->getFirstSegment())->string('subquery') !== 'exists') {
                $paths[] = $path;
            }
        }

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
        $joins = $this->readOrmJoins($qb);

        foreach ($joins as $joinList) {
            foreach ($joinList as $join) {
                if (strtolower($join->getAlias() ?? '') === strtolower($alias)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<array-key,array<array-key,Join>>
     */
    private function readOrmJoins(DoctrineORMQueryBuilder $qb): array
    {
        $joins = $qb->getDQLPart('join');
        if (!is_array($joins)) {
            throw new RuntimeException('Unexpected Doctrine ORM QueryBuilder join state.');
        }

        $validated = [];
        foreach ($joins as $alias => $group) {
            if (!is_array($group)) {
                throw new RuntimeException('Unexpected Doctrine ORM QueryBuilder join state.');
            }
            $validatedGroup = [];
            foreach ($group as $key => $join) {
                if (!$join instanceof Join) {
                    throw new RuntimeException('Unexpected Doctrine ORM QueryBuilder join state.');
                }
                $validatedGroup[$key] = $join;
            }
            $validated[$alias] = $validatedGroup;
        }

        return $validated;
    }
}
