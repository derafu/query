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

use Derafu\Query\Bridge\DoctrineORM\NumericComparisonType;
use Derafu\Query\Bridge\DoctrineORMQueryBuilderConditionApplier;
use Derafu\Query\Bridge\Exception\UnsupportedOperatorException;
use Derafu\Query\Builder\AliasScope;
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
use Derafu\TestsQuery\Integration\Bridge\DoctrineORM\Entity\Invoice;
use Derafu\Translation\Exception\Core\TranslatableRuntimeException;
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
use stdClass;
use Throwable;

#[UsesClass(AliasScope::class)]
#[CoversClass(DoctrineORMQueryBuilderConditionApplier::class)]
#[UsesClass(NumericComparisonType::class)]
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
        $sql = file_get_contents(
            __DIR__ . '/../../../../fixtures/integration/billing_schema.sql'
        );
        $this->assertIsString($sql);
        $pdo->exec($sql);
        $sql = file_get_contents(
            __DIR__ . '/../../../../fixtures/integration/billing_data.sql'
        );
        $this->assertIsString($sql);
        $pdo->exec($sql);

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

    /**
     * @param array{sql:string,parameters:array<string,mixed>} $sql
     * @param array{table?:class-string,alias?:string,select?:string,distinct?:bool,where?:string|list<string|list<string>>,andWhere?:string|list<string|list<string>>,orWhere?:string|list<string|list<string>>,andWhereOr?:list<string|list<string>>,having?:string|list<string|list<string>>,composite?:string,innerJoin?:array{join:string,alias:string},leftJoin?:array{join:string,alias:string},groupBy?:string|list<string>,orderBy?:array<string,string>,limit?:int,offset?:int} $query
     */
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

    /**
     * @return iterable<string,array{string}>
     */
    public static function correlatedSubqueries(): iterable
    {
        yield 'column filter' => ['___invoices__status?=paid'];
        yield 'sum aggregate' => ['___invoices__SUM(total)?>1000'];
        yield 'count aggregate' => ['___invoices__COUNT(*)?>1'];
    }

    #[DataProvider('correlatedSubqueries')]
    public function testCorrelatedSubqueriesRequireARootEntity(string $expression): void
    {
        $condition = $this->expressionParser->parse($expression);
        $qb = $this->em->createQueryBuilder()->select('1');
        $dql = $qb->getDQL();

        $this->expectException(TranslatableRuntimeException::class);
        $this->expectExceptionMessage('A correlated DQL subquery requires a root entity in the FROM clause.');
        try {
            $this->applier->apply($qb, $condition);
        } finally {
            $this->assertSame($dql, $qb->getDQL());
            $this->assertCount(0, $qb->getParameters());
        }
    }

    public function testExistsOnTheOwningSideFiltersByTheRelatedCustomer(): void
    {
        $condition = $this->expressionParser->parse('___customer__status?=active');
        $qb = $this->em->createQueryBuilder()
            ->select('i.id')
            ->from(Invoice::class, 'i')
            ->orderBy('i.id');
        $this->applier->apply($qb, $condition);

        // Invoice 5 belongs to the inactive customer 3 and must be excluded.
        $this->assertSame(
            [['id' => 1], ['id' => 2], ['id' => 3], ['id' => 4], ['id' => 6]],
            $qb->getQuery()->getScalarResult()
        );
    }

    public function testExistingOrmJoinIsNotAddedAgain(): void
    {
        $qb = $this->em->createQueryBuilder()
            ->select('c.id')
            ->from(Customer::class, 'c')
            ->innerJoin('c.invoices', 'i')
            ->orderBy('c.id');
        $condition = $this->expressionParser->parse('customers[alias:c]__invoices[alias:i]__status?=paid');
        $this->applier->apply($qb, $condition);
        $this->applier->apply($qb, $condition);

        $this->assertSame(1, substr_count($qb->getDQL(), 'JOIN c.invoices i'));
        $this->assertSame([['id' => 1], ['id' => 2]], $qb->getQuery()->getScalarResult());
    }

    /**
     * @return iterable<string,array{stdClass}>
     */
    public static function invalidOrmJoinParts(): iterable
    {
        yield 'group is not an array' => [(object) ['c' => 'invalid']];
        yield 'entry is not an ORM join' => [(object) ['c' => [new stdClass()]]];
    }

    #[DataProvider('invalidOrmJoinParts')]
    public function testMalformedOrmJoinPartsAreRejected(stdClass $joins): void
    {
        $qb = $this->em->createQueryBuilder()->select('c.id')->from(Customer::class, 'c');
        $qb->add('join', $joins);
        $condition = $this->expressionParser->parse('customers[alias:c]__invoices[alias:i]__status?=paid');

        $this->expectException(TranslatableRuntimeException::class);
        $this->expectExceptionMessage('Unexpected Doctrine ORM QueryBuilder join state.');
        $this->applier->apply($qb, $condition);
    }

    public function testAggregateSubqueryReplacesItsMarkerAndExecutes(): void
    {
        $condition = $this->expressionParser->parse('___invoices__SUM(total)?isnot:null');
        $qb = $this->em->createQueryBuilder()
            ->select('c.id')
            ->from(Customer::class, 'c')
            ->orderBy('c.id');
        $this->applier->apply($qb, $condition);

        $this->assertStringNotContainsString('DERAFUAGG', $qb->getDQL());
        $this->assertMatchesRegularExpression('/\(SELECT SUM\([a-z_][a-z0-9_]*\.total\)/i', $qb->getDQL());
        $this->assertCount(0, $qb->getParameters());
        $this->assertSame(
            [['id' => 1], ['id' => 2], ['id' => 3], ['id' => 4]],
            $qb->getQuery()->getScalarResult()
        );
    }

    /**
     * @return iterable<string,array{string,string,list<int>}>
     */
    public static function aggregateNullChecks(): iterable
    {
        foreach (['SUM(total)', 'AVG(total)', 'MIN(total)', 'MAX(total)', 'COUNT(total)', 'COUNT(*)'] as $aggregate) {
            foreach (['is:null', 'isnot:null', '<=>'] as $operator) {
                $isNotNull = $operator === 'isnot:null';
                $expectedIds = str_starts_with($aggregate, 'COUNT(')
                    ? ($isNotNull ? [1, 2, 3, 4, 5, 6, 7, 8, 9] : [])
                    : ($isNotNull ? [1, 2, 3, 4, 7, 8, 9] : [5, 6]);

                yield $aggregate . ' ' . $operator => [$aggregate, $operator, $expectedIds];
            }
        }
    }

    /**
     * @param list<int> $expectedIds
     */
    #[DataProvider('aggregateNullChecks')]
    public function testAggregateNullChecksPreserveSqlSemantics(string $aggregate, string $operator, array $expectedIds): void
    {
        // Customer 5 has no invoices. Add all-null, zero, cancelling and mixed
        // totals to distinguish SQL null semantics from emptiness or truthiness.
        $this->connection->executeStatement(
            "INSERT INTO customers(id,name) VALUES (6,'All null'),(7,'Zero'),(8,'Cancelling'),(9,'Mixed')"
        );
        $this->connection->executeStatement(
            'INSERT INTO invoices(id,customer_id,total) VALUES '
            . '(7,6,NULL),(8,7,0),(9,8,10),(10,8,-10),(11,9,NULL),(12,9,30)'
        );
        $condition = $this->expressionParser->parse('___invoices__' . $aggregate . '?' . $operator);
        $qb = $this->em->createQueryBuilder()
            ->select('c.id')
            ->from(Customer::class, 'c')
            ->orderBy('c.id');
        $this->applier->apply($qb, $condition);

        $this->assertStringContainsString('EXISTS (SELECT ', $qb->getDQL());
        $this->assertStringNotContainsString('DERAFUAGG', $qb->getDQL());
        $this->assertCount(0, $qb->getParameters());
        $actual = $qb->getQuery()->getScalarResult();
        $this->assertSame(array_map(static fn (int $id): array => ['id' => $id], $expectedIds), $actual);

        $sqlAggregate = str_replace('total', 'i.total', $aggregate);
        $predicate = $operator === 'isnot:null' ? 'IS NOT NULL' : 'IS NULL';
        $expected = $this->connection->fetchAllAssociative(
            'SELECT c.id FROM customers c WHERE (SELECT ' . $sqlAggregate
            . ' FROM invoices i WHERE i.customer_id = c.id) ' . $predicate . ' ORDER BY c.id'
        );
        $this->assertSame($expected, $actual);
    }

    /**
     * @return iterable<string,array{string,?string,string}>
     */
    public static function countComparisonValues(): iterable
    {
        yield 'zero with whitespace' => ['=', ' 0 ', '= 0'];
        yield 'one with whitespace' => ['>=', ' 1 ', '> 0'];
        yield 'empty with null' => ['is:empty', null, '= 0'];
        yield 'not empty with null' => ['isnot:empty', null, '> 0'];
    }

    #[DataProvider('countComparisonValues')]
    public function testCountOptimizationPreservesNullAndWhitespace(string $symbol, ?string $value, string $comparison): void
    {
        $operators = (new OperatorLoader())->loadFromFile(__DIR__ . '/../../../../../resources/operators.yaml');
        $condition = new Condition(
            (new PathParser())->parse('___invoices__COUNT(*)'),
            new Filter($operators[$symbol], $value)
        );
        $qb = $this->em->createQueryBuilder()
            ->select('c.id')
            ->from(Customer::class, 'c')
            ->orderBy('c.id');
        $this->applier->apply($qb, $condition);

        $this->assertStringContainsString('SIZE(c.invoices) ' . $comparison, $qb->getDQL());
        $this->assertCount(0, $qb->getParameters());
        $expected = $this->connection->fetchAllAssociative(
            'SELECT c.id FROM customers c WHERE (SELECT COUNT(*) FROM invoices i WHERE i.customer_id = c.id) '
            . $comparison . ' ORDER BY c.id'
        );
        $this->assertSame(
            $this->normalizeResults($expected, true),
            $this->normalizeResults($qb->getQuery()->getScalarResult(), true)
        );
    }

    /**
     * @param class-string<Throwable> $exception
     */
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

    /**
     * @return array<string,array{string,array{sql:string,parameters:array<string,mixed>},array{table?:class-string,alias?:string,select?:string,distinct?:bool,where?:string|list<string|list<string>>,andWhere?:string|list<string|list<string>>,orWhere?:string|list<string|list<string>>,andWhereOr?:list<string|list<string>>,having?:string|list<string|list<string>>,composite?:string,innerJoin?:array{join:string,alias:string},leftJoin?:array{join:string,alias:string},groupBy?:string|list<string>,orderBy?:array<string,string>,limit?:int,offset?:int},bool}>
     */
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

    /**
     * @return array<string,array{string,string,class-string<Throwable>}>
     */
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
     *
     * @param array<mixed> $results
     * @return array<array<string|int,string|null>>
     */
    private function normalizeResults(array $results, bool $preserveOrder): array
    {
        $normalized = array_map(
            function (mixed $row): array {
                $this->assertIsArray($row);

                return array_map(
                    function (mixed $value): ?string {
                        if ($value === null) {
                            return null;
                        }
                        $this->assertIsScalar($value);

                        return (string) $value;
                    },
                    $row
                );
            },
            $results
        );

        if (!$preserveOrder) {
            usort($normalized, fn ($a, $b) => json_encode($a) <=> json_encode($b));
        }

        return $normalized;
    }

    /**
     * Builds a Doctrine ORM QueryBuilder from a query config array.
     *
     * @param array{table?:class-string,alias?:string,select?:string,distinct?:bool,where?:string|list<string|list<string>>,andWhere?:string|list<string|list<string>>,orWhere?:string|list<string|list<string>>,andWhereOr?:list<string|list<string>>,having?:string|list<string|list<string>>,composite?:string,innerJoin?:array{join:string,alias:string},leftJoin?:array{join:string,alias:string},groupBy?:string|list<string>,orderBy?:array<string,string>,limit?:int,offset?:int} $config
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
     *
     * @param array{table?:class-string,alias?:string,select?:string,distinct?:bool,where?:string|list<string|list<string>>,andWhere?:string|list<string|list<string>>,orWhere?:string|list<string|list<string>>,andWhereOr?:list<string|list<string>>,having?:string|list<string|list<string>>,composite?:string,innerJoin?:array{join:string,alias:string},leftJoin?:array{join:string,alias:string},groupBy?:string|list<string>,orderBy?:array<string,string>,limit?:int,offset?:int} $config
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

    /**
     * @param string|list<string|list<string>> $conditions
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
     * @param string|list<string|list<string>> $conditions
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

    /** @return iterable<string, array{'pdo_oci'|'pdo_sqlsrv'}> */
    public static function compilationDrivers(): iterable
    {
        yield 'oracle' => ['pdo_oci'];
        yield 'sql server' => ['pdo_sqlsrv'];
    }

    /** @param 'pdo_oci'|'pdo_sqlsrv' $driver */
    #[DataProvider('compilationDrivers')]
    public function testConditionsAndPaginationCompileWithoutConnectingToTheServer(string $driver): void
    {
        $config = ORMSetup::createConfig(false, null, new ArrayCachePool());
        $config->setMetadataDriverImpl(new AttributeDriver([__DIR__ . '/Entity']));
        $config->enableNativeLazyObjects(true);
        $connection = DriverManager::getConnection(['driver' => $driver], $config);
        $em = new EntityManager($connection, $config);
        try {
            $qb = $em->createQueryBuilder()->select('c.id')->from(Customer::class, 'c')
                ->orderBy('c.id')->setFirstResult(2)->setMaxResults(5);
            $this->applier->apply($qb, $this->expressionParser->parse('status?=active'));
            $sql = $qb->getQuery()->getSQL();

            $this->assertIsString($sql);
            $this->assertMatchesRegularExpression('/WHERE [a-z0-9_]+\.status = \?/', $sql);
            $this->assertStringEndsWith('OFFSET 2 ROWS FETCH NEXT 5 ROWS ONLY', $sql);
            $this->assertCount(1, $qb->getParameters());
            foreach ($qb->getParameters() as $parameter) {
                $this->assertSame('active', $parameter->getValue());
            }
            $this->assertFalse($connection->isConnected());
        } finally {
            $em->close();
            $connection->close();
        }
    }
}
