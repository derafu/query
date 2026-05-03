<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsQuery\Integration\Bridge;

use Derafu\Query\Bridge\DoctrineDBALQueryBuilderConditionApplier;
use Derafu\Query\Builder\Sql\SqlBuilderWhere;
use Derafu\Query\Builder\Sql\SqlQuery;
use Derafu\Query\Filter\CompositeCondition;
use Derafu\Query\Filter\Condition;
use Derafu\Query\Filter\Contract\CompositeConditionInterface;
use Derafu\Query\Filter\Contract\ExpressionParserInterface;
use Derafu\Query\Filter\ExpressionParser;
use Derafu\Query\Filter\Filter;
use Derafu\Query\Filter\FilterParser;
use Derafu\Query\Filter\Path;
use Derafu\Query\Filter\PathParser;
use Derafu\Query\Filter\Segment;
use Derafu\Query\Operator\Operator;
use Derafu\Query\Operator\OperatorLoader;
use Derafu\Query\Operator\OperatorManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Query\QueryBuilder as DoctrineDBALQueryBuilder;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DoctrineDBALQueryBuilderConditionApplier::class)]
#[UsesClass(SqlBuilderWhere::class)]
#[UsesClass(SqlQuery::class)]
#[UsesClass(CompositeCondition::class)]
#[UsesClass(Condition::class)]
#[UsesClass(ExpressionParser::class)]
#[UsesClass(Filter::class)]
#[UsesClass(FilterParser::class)]
#[UsesClass(Path::class)]
#[UsesClass(PathParser::class)]
#[UsesClass(Segment::class)]
#[UsesClass(Operator::class)]
#[UsesClass(OperatorLoader::class)]
#[UsesClass(OperatorManager::class)]
class DoctrineDBALQueryBuilderSqliteTest extends TestCase
{
    private Connection $connection;

    private DoctrineDBALQueryBuilderConditionApplier $applier;

    private ExpressionParserInterface $expressionParser;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $pdo = $this->connection->getNativeConnection();
        assert($pdo instanceof PDO);
        $pdo->exec(file_get_contents(
            __DIR__ . '/../../../fixtures/integration/billing_schema.sql'
        ));
        $pdo->exec(file_get_contents(
            __DIR__ . '/../../../fixtures/integration/billing_data.sql'
        ));

        $pathParser = new PathParser();
        $loader = new OperatorLoader();
        $operators = $loader->loadFromFile(
            __DIR__ . '/../../../../resources/operators.yaml'
        );
        $manager = new OperatorManager($operators);
        $filterParser = new FilterParser($manager);
        $this->expressionParser = new ExpressionParser($pathParser, $filterParser);

        $this->applier = new DoctrineDBALQueryBuilderConditionApplier();
    }

    #[DataProvider('queryProvider')]
    public function testSqlQueries(
        string $description,
        array $sql,
        array $query
    ): void {
        $expected = $this->connection->fetchAllAssociative($sql['sql'], $sql['parameters']);

        $qb = $this->buildQueryFromConfig($query);
        $actual = $qb->fetchAllAssociative();

        $this->assertSame($expected, $actual, $description);
    }

    public static function queryProvider(): array
    {
        $cases = require __DIR__ . '/../../../fixtures/integration/queries_integration.php';
        $data = [];

        foreach ($cases['cases'] as $name => $case) {
            $data[$name] = [
                $case['description'],
                $case['sql'],
                $case['query'],
            ];
        }

        return $data;
    }

    /**
     * Builds a Doctrine DBAL QueryBuilder from a query config array.
     */
    private function buildQueryFromConfig(array $config): DoctrineDBALQueryBuilder
    {
        $qb = $this->connection->createQueryBuilder();

        // FROM / table.
        if (isset($config['table'])) {
            $alias = $config['alias'] ?? null;
            $qb->from($config['table'], $alias);
        }

        // SELECT.
        if (isset($config['select'])) {
            $qb->select((string) $config['select']);
        } else {
            $qb->select('*');
        }

        // DISTINCT.
        if (isset($config['distinct']) && $config['distinct']) {
            $qb->distinct();
        }

        // Explicit JOINs (non-path based).
        foreach (['innerJoin', 'leftJoin', 'rightJoin'] as $joinType) {
            if (!isset($config[$joinType])) {
                continue;
            }
            $join = $config[$joinType];
            $fromAlias = $config['alias'] ?? $config['table'];
            $joinAlias = $join['alias'] ?? $join['table'];
            match ($joinType) {
                'innerJoin' => $qb->innerJoin($fromAlias, $join['table'], $joinAlias, $join['condition']),
                'leftJoin' => $qb->leftJoin($fromAlias, $join['table'], $joinAlias, $join['condition']),
                'rightJoin' => $qb->rightJoin($fromAlias, $join['table'], $joinAlias, $join['condition']),
            };
        }

        // WHERE conditions via the applier.
        $composite = $this->buildWhereComposite($config);
        if ($composite !== null) {
            $this->applier->apply($qb, $composite);
        }

        // HAVING conditions via the applier.
        if (isset($config['having'])) {
            $havingComposite = CompositeCondition::and();
            $this->addToComposite($havingComposite, $config['having']);
            $this->applier->applyHaving($qb, $havingComposite);
        }

        // GROUP BY.
        if (isset($config['groupBy'])) {
            $groups = is_array($config['groupBy'])
                ? $config['groupBy']
                : [$config['groupBy']];
            $qb->groupBy(...$groups);
        }

        // ORDER BY.
        if (isset($config['orderBy'])) {
            foreach ($config['orderBy'] as $column => $direction) {
                $qb->addOrderBy($column, $direction);
            }
        }

        // LIMIT / OFFSET.
        if (isset($config['limit'])) {
            $qb->setMaxResults($config['limit']);
        }
        if (isset($config['offset'])) {
            $qb->setFirstResult($config['offset']);
        }

        return $qb;
    }

    /**
     * Builds a CompositeCondition from the where/andWhere/orWhere/andWhereOr
     * config keys, following the same composition logic as SqlQueryBuilder.
     */
    private function buildWhereComposite(array $config): ?CompositeConditionInterface
    {
        $composite = null;

        // where.
        if (isset($config['where'])) {
            $composite = CompositeCondition::and();
            $this->addToComposite($composite, $config['where']);
        }

        // andWhere is applied before orWhere (matches QueryConfig::applyTo order).
        if (isset($config['andWhere'])) {
            if ($composite === null) {
                $composite = CompositeCondition::and();
            }
            $this->addToComposite($composite, $config['andWhere']);
        }

        // orWhere wraps the existing composite in an OR.
        if (isset($config['orWhere'])) {
            if ($composite === null) {
                $composite = CompositeCondition::and();
                $this->addToComposite($composite, $config['orWhere']);
            } else {
                $existing = $composite;
                $composite = CompositeCondition::or();
                $composite->add($existing);
                $this->addOrGroupToComposite($composite, $config['orWhere']);
            }
        }

        // andWhereOr adds an OR group ANDed to the current composite.
        if (isset($config['andWhereOr'])) {
            if ($composite === null) {
                $composite = CompositeCondition::and();
            }
            $or = CompositeCondition::or();
            foreach ($config['andWhereOr'] as $group) {
                $and = CompositeCondition::and();
                $this->addToComposite($and, $group);
                $or->add($and);
            }
            $composite->add($or);
        }

        return $composite;
    }

    /**
     * Adds one or more expression strings (or arrays of them) to a composite.
     */
    private function addToComposite(
        CompositeConditionInterface $composite,
        string|array $conditions
    ): void {
        if (is_string($conditions)) {
            $composite->add($this->expressionParser->parse($conditions));
            return;
        }

        foreach ($conditions as $condition) {
            if (is_string($condition)) {
                $composite->add($this->expressionParser->parse($condition));
            } elseif (is_array($condition)) {
                $sub = CompositeCondition::and();
                $this->addToComposite($sub, $condition);
                $composite->add($sub);
            }
        }
    }

    /**
     * Adds orWhere value to an OR composite, handling flat arrays and
     * arrays-of-arrays (multiple OR groups).
     */
    private function addOrGroupToComposite(
        CompositeConditionInterface $orComposite,
        string|array $conditions
    ): void {
        if (is_string($conditions)) {
            $and = CompositeCondition::and();
            $and->add($this->expressionParser->parse($conditions));
            $orComposite->add($and);
            return;
        }

        // Check if any element is itself an array (multiple OR groups).
        $hasSubArrays = false;
        foreach ($conditions as $item) {
            if (is_array($item)) {
                $hasSubArrays = true;
                break;
            }
        }

        if ($hasSubArrays) {
            foreach ($conditions as $item) {
                $and = CompositeCondition::and();
                $this->addToComposite($and, is_array($item) ? $item : [$item]);
                $orComposite->add($and);
            }
        } else {
            $and = CompositeCondition::and();
            $this->addToComposite($and, $conditions);
            $orComposite->add($and);
        }
    }
}
