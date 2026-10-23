<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsQuery\Integration\Engine;

use Derafu\Query\Builder\AliasScope;
use Derafu\Query\Builder\Contract\QueryBuilderInterface;
use Derafu\Query\Builder\Sql\SqlBuilderWhere;
use Derafu\Query\Builder\Sql\SqlQuery;
use Derafu\Query\Builder\SqlQueryBuilder;
use Derafu\Query\Config\QueryConfig;
use Derafu\Query\Engine\Contract\SqlEngineInterface;
use Derafu\Query\Engine\DoctrineEngine;
use Derafu\Query\Filter\CompositeCondition;
use Derafu\Query\Filter\Condition;
use Derafu\Query\Filter\ExpressionParser;
use Derafu\Query\Filter\Filter;
use Derafu\Query\Filter\FilterParser;
use Derafu\Query\Filter\Path;
use Derafu\Query\Filter\PathParser;
use Derafu\Query\Filter\Segment;
use Derafu\Query\Operator\Operator;
use Derafu\Query\Operator\OperatorLoader;
use Derafu\Query\Operator\OperatorManager;
use Derafu\Translation\Exception\Core\TranslatableRuntimeException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager as DoctrineDriverManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SQLite3;

#[UsesClass(AliasScope::class)]
#[CoversClass(QueryConfig::class)]
#[CoversClass(SqlQueryBuilder::class)]
#[CoversClass(SqlBuilderWhere::class)]
#[CoversClass(SqlQuery::class)]
#[CoversClass(Filter::class)]
#[CoversClass(FilterParser::class)]
#[CoversClass(Path::class)]
#[CoversClass(PathParser::class)]
#[CoversClass(Segment::class)]
#[CoversClass(Operator::class)]
#[CoversClass(OperatorLoader::class)]
#[CoversClass(OperatorManager::class)]
#[CoversClass(ExpressionParser::class)]
#[CoversClass(Condition::class)]
#[CoversClass(CompositeCondition::class)]
#[CoversClass(DoctrineEngine::class)]
class DoctrineSqliteIntegrationTest extends TestCase
{
    private SqlEngineInterface $engine;

    private QueryBuilderInterface $query;

    protected function setUp(): void
    {
        // Create in-memory SQLite database and load schema and test data.
        $this->engine = new DoctrineEngine(
            DoctrineDriverManager::getConnection([
                'driver' => 'pdo_sqlite',
            ])
        );
        $schema = file_get_contents(__DIR__ . '/../../../fixtures/integration/billing_schema.sql');
        $this->assertIsString($schema);
        $this->engine->executeSqlDump($schema);
        $data = file_get_contents(__DIR__ . '/../../../fixtures/integration/billing_data.sql');
        $this->assertIsString($data);
        $this->engine->executeSqlDump($data);

        // Create query builder.
        $pathParser = new PathParser();
        $loader = new OperatorLoader();
        $operators = $loader->loadFromFile(__DIR__ . '/../../../../resources/operators.yaml');
        $manager = new OperatorManager($operators);
        $filterParser = new FilterParser($manager);
        $expressionParser = new ExpressionParser($pathParser, $filterParser);
        $this->query = new SqlQueryBuilder($this->engine, $expressionParser);
    }

    public function testNativeConnectionRemainsAvailableToTheConsumer(): void
    {
        $connection = $this->engine->getConnection();
        $this->assertInstanceOf(Connection::class, $connection);
        $this->assertSame(5, $connection->fetchOne('SELECT COUNT(*) FROM customers'));
        $connection->executeStatement("UPDATE customers SET status = 'active' WHERE id = 3");
        $this->assertSame([['status' => 'active']], $this->engine->execute('SELECT status FROM customers WHERE id = 3'));
    }

    /**
     * @param array{sql:string,parameters:array<string,mixed>} $sql
     * @param array{table?:string,alias?:string,select?:string,distinct?:bool,where?:string|list<string|list<string>>,andWhere?:string|list<string|list<string>>,orWhere?:string|list<string|list<string>>,andWhereOr?:string|list<string|list<string>>,having?:string|list<string|list<string>>,composite?:string,innerJoin?:array{table:string,condition:string,alias?:string},leftJoin?:array{table:string,condition:string,alias?:string},rightJoin?:array{table:string,condition:string,alias?:string},groupBy?:string|list<string>,orderBy?:array<string,string>,limit?:int,offset?:int} $query
     */
    #[DataProvider('queryProvider')]
    public function testSqlQueries(
        string $description,
        array $sql,
        array $query
    ): void {
        // Execute raw SQL.
        $expected = $this->engine->execute($sql['sql'], $sql['parameters']);

        // Build query using ConfigQuery and the QueryBuilder, then execute.
        $config = new QueryConfig($query);
        $builder = $config->applyTo($this->query->new());
        $actual = $builder->execute();

        // Compare results.
        $this->assertSame($expected, $actual, $description);
    }

    /**
     * @return array<string,array{string,array{sql:string,parameters:array<string,mixed>},array{table?:string,alias?:string,select?:string,distinct?:bool,where?:string|list<string|list<string>>,andWhere?:string|list<string|list<string>>,orWhere?:string|list<string|list<string>>,andWhereOr?:string|list<string|list<string>>,having?:string|list<string|list<string>>,composite?:string,innerJoin?:array{table:string,condition:string,alias?:string},leftJoin?:array{table:string,condition:string,alias?:string},rightJoin?:array{table:string,condition:string,alias?:string},groupBy?:string|list<string>,orderBy?:array<string,string>,limit?:int,offset?:int}}>
     */
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

    public function testGetTable(): void
    {
        $customers = $this->engine->getTable(
            'SELECT * FROM customers'
        );

        $this->assertNotEmpty($customers);
        $this->assertArrayHasKey('id', $customers[0]);
        $this->assertArrayHasKey('name', $customers[0]);
    }

    public function testGetRow(): void
    {
        $customer = $this->engine->getRow(
            'SELECT * FROM customers WHERE id = :id',
            ['id' => 1]
        );

        $this->assertArrayHasKey('id', $customer);
        $this->assertSame(1, $customer['id']);
    }

    public function testGetCol(): void
    {
        $names = $this->engine->getCol('SELECT name FROM customers');

        $this->assertNotEmpty($names);
        $this->assertContains('Acme Corp', $names);
    }

    public function testGetValue(): void
    {
        $name = $this->engine->getValue(
            'SELECT name FROM customers WHERE id = :id',
            ['id' => 3]
        );

        $this->assertNotNull($name);
        $this->assertSame('Jane Doe', $name);
    }

    /** @return iterable<string, array{'pdo_oci'|'pdo_sqlsrv', 'oci'|'sqlsrv'}> */
    public static function compilationDrivers(): iterable
    {
        yield 'oracle' => ['pdo_oci', 'oci'];
        yield 'sql server' => ['pdo_sqlsrv', 'sqlsrv'];
    }

    /** @param 'pdo_oci'|'pdo_sqlsrv' $driver */
    #[DataProvider('compilationDrivers')]
    public function testTheDriverIsResolvedWithoutOpeningAServerConnection(string $driver, string $expected): void
    {
        $connection = DoctrineDriverManager::getConnection(['driver' => $driver]);
        try {
            $engine = new DoctrineEngine($connection);
            $this->assertSame($expected, $engine->getDriver());
            $this->assertSame($connection, $engine->getConnection());
            $this->assertFalse($connection->isConnected());
        } finally {
            $connection->close();
        }
    }

    public function testSqlDumpsRejectANativeSqlite3ConnectionBeforeApplyingStatements(): void
    {
        if (!class_exists(SQLite3::class)) {
            $this->markTestSkipped('The SQLite3 extension is required for this native-driver integration test.');
        }

        $connection = DoctrineDriverManager::getConnection(['driver' => 'sqlite3', 'memory' => true]);
        try {
            $engine = new DoctrineEngine($connection);
            $this->assertInstanceOf(SQLite3::class, $connection->getNativeConnection());
            $this->assertSame([['id' => 1]], $engine->execute('SELECT 1 AS id'));
            $this->expectException(TranslatableRuntimeException::class);
            $this->expectExceptionMessage('Doctrine DBAL native connection is not a PDO instance.');
            $engine->executeSqlDump('CREATE TABLE imported (id INTEGER)');
        } finally {
            $this->assertSame(0, $connection->fetchOne("SELECT COUNT(*) FROM sqlite_master WHERE name = 'imported'"));
            $connection->close();
        }
    }
}
