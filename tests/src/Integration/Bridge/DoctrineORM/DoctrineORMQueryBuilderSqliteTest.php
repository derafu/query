<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsQuery\Integration\Bridge\DoctrineORM;

use Derafu\Query\Bridge\DoctrineORMQueryBuilderConditionApplier;
use Derafu\Query\Bridge\Exception\UnsupportedOperatorException;
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
use Derafu\TestsQuery\Integration\Bridge\DoctrineORM\Entity\Customer;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\QueryBuilder as DoctrineORMQueryBuilder;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DoctrineORMQueryBuilderConditionApplier::class)]
#[UsesClass(UnsupportedOperatorException::class)]
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
class DoctrineORMQueryBuilderSqliteTest extends TestCase
{
    private Connection $connection;

    private EntityManager $em;

    private DoctrineORMQueryBuilderConditionApplier $applier;

    private ExpressionParserInterface $expressionParser;

    protected function setUp(): void
    {
        $cache = new ArrayCachePool();
        $config = ORMSetup::createConfig(false, null, $cache);
        $config->setMetadataDriverImpl(new AttributeDriver([__DIR__ . '/Entity']));
        $config->enableNativeLazyObjects(true);

        $this->connection = DriverManager::getConnection(
            ['driver' => 'pdo_sqlite', 'memory' => true],
            $config
        );

        $this->em = new EntityManager($this->connection, $config);

        $pdo = $this->connection->getNativeConnection();
        assert($pdo instanceof PDO);
        $pdo->exec(file_get_contents(
            __DIR__ . '/../../../../fixtures/integration/billing_schema.sql'
        ));
        $pdo->exec(file_get_contents(
            __DIR__ . '/../../../../fixtures/integration/billing_data.sql'
        ));

        $pathParser = new PathParser();
        $loader = new OperatorLoader();
        $operators = $loader->loadFromFile(
            __DIR__ . '/../../../../../resources/operators.yaml'
        );
        $manager = new OperatorManager($operators);
        $filterParser = new FilterParser($manager);
        $this->expressionParser = new ExpressionParser($pathParser, $filterParser);

        $this->applier = new DoctrineORMQueryBuilderConditionApplier();
    }

    #[DataProvider('queryProvider')]
    public function testSqlQueries(
        string $description,
        array $sql,
        array $query,
        bool $preserveOrder
    ): void {
        $expected = $this->connection->fetchAllAssociative($sql['sql'], $sql['parameters']);
        $qb = $this->buildQueryFromConfig($query);
        $actual = $qb->getQuery()->getScalarResult();

        $expected = $this->normalizeResults($expected, $preserveOrder);
        $actual = $this->normalizeResults($actual, $preserveOrder);

        $this->assertSame($expected, $actual, $description);
    }

    #[DataProvider('exceptionProvider')]
    public function testUnsupportedOperatorsThrow(
        string $description,
        string $expression,
        string $exception
    ): void {
        $condition = $this->expressionParser->parse($expression);

        $qb = $this->em->createQueryBuilder()
            ->select('c.id')
            ->from(Customer::class, 'c');

        $this->expectException($exception);
        $this->applier->apply($qb, $condition);
    }

    public static function queryProvider(): array
    {
        $cases = require __DIR__ . '/../../../../fixtures/integration/doctrine_orm.php';
        $data = [];

        foreach ($cases['cases'] as $name => $case) {
            $data[$name] = [
                $case['description'],
                $case['sql'],
                $case['query'],
                $case['preserveOrder'] ?? false,
            ];
        }

        return $data;
    }

    public static function exceptionProvider(): array
    {
        $cases = require __DIR__ . '/../../../../fixtures/integration/doctrine_orm.php';
        $data = [];

        foreach ($cases['exception_cases'] as $name => $case) {
            $data[$name] = [
                $case['description'],
                $case['expression'],
                $case['exception'],
            ];
        }

        return $data;
    }

    /**
     * Normalizes result rows to comparable string arrays.
     */
    private function normalizeResults(array $results, bool $preserveOrder): array
    {
        $normalized = array_map(
            fn (array $row) => array_map(
                fn ($v) => $v === null ? null : (string) $v,
                $row
            ),
            $results
        );

        if (!$preserveOrder) {
            usort($normalized, fn ($a, $b) => json_encode($a) <=> json_encode($b));
        }

        return $normalized;
    }

    /**
     * Builds a Doctrine ORM QueryBuilder from a query config array.
     */
    private function buildQueryFromConfig(array $config): DoctrineORMQueryBuilder
    {
        $qb = $this->em->createQueryBuilder();

        // FROM / entity.
        if (isset($config['table'])) {
            $alias = $config['alias'] ?? 'e';
            $qb->from($config['table'], $alias);
        }

        // SELECT.
        if (isset($config['select'])) {
            $qb->select($config['select']);
        }

        // DISTINCT.
        if (isset($config['distinct']) && $config['distinct']) {
            $qb->distinct();
        }

        // Explicit JOINs (non-path based).
        foreach (['innerJoin', 'leftJoin'] as $joinType) {
            if (!isset($config[$joinType])) {
                continue;
            }
            $join = $config[$joinType];
            match ($joinType) {
                'innerJoin' => $qb->innerJoin($join['join'], $join['alias']),
                'leftJoin' => $qb->leftJoin($join['join'], $join['alias']),
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
            $first = true;
            foreach ($config['orderBy'] as $column => $direction) {
                if ($first) {
                    $qb->orderBy($column, $direction);
                    $first = false;
                } else {
                    $qb->addOrderBy($column, $direction);
                }
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
     * Builds a CompositeCondition from the where/andWhere/orWhere/andWhereOr config keys.
     */
    private function buildWhereComposite(array $config): ?CompositeConditionInterface
    {
        $composite = null;

        if (isset($config['where'])) {
            $composite = CompositeCondition::and();
            $this->addToComposite($composite, $config['where']);
        }

        if (isset($config['andWhere'])) {
            if ($composite === null) {
                $composite = CompositeCondition::and();
            }
            $this->addToComposite($composite, $config['andWhere']);
        }

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
