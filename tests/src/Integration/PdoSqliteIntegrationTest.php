<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsQuery\Integration;

use Derafu\Query\Builder\Contract\QueryBuilderInterface;
use Derafu\Query\Builder\Sql\SqlBuilderWhere;
use Derafu\Query\Builder\Sql\SqlQuery;
use Derafu\Query\Builder\SqlQueryBuilder;
use Derafu\Query\Config\QueryConfig;
use Derafu\Query\Engine\Contract\SqlEngineInterface;
use Derafu\Query\Engine\PdoEngine;
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
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

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
#[CoversClass(PdoEngine::class)]
class PdoSqliteIntegrationTest extends TestCase
{
    private SqlEngineInterface $engine;

    private QueryBuilderInterface $query;

    protected function setUp(): void
    {
        // Create in-memory SQLite database and load schema and test data.
        $this->engine = new PdoEngine(new PDO('sqlite::memory:'));
        $schema = file_get_contents(__DIR__ . '/../../fixtures/integration/billing_schema.sql');
        $this->engine->executeSqlDump($schema);
        $data = file_get_contents(__DIR__ . '/../../fixtures/integration/billing_data.sql');
        $this->engine->executeSqlDump($data);

        // Create query builder.
        $pathParser = new PathParser();
        $loader = new OperatorLoader();
        $operators = $loader->loadFromFile(__DIR__ . '/../../../resources/operators.yaml');
        $manager = new OperatorManager($operators);
        $filterParser = new FilterParser($manager);
        $expressionParser = new ExpressionParser($pathParser, $filterParser);
        $this->query = new SqlQueryBuilder($this->engine, $expressionParser);
    }

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

    public static function queryProvider(): array
    {
        $cases = require __DIR__ . '/../../fixtures/integration/queries_integration.php';
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

    public function testGetTable()
    {
        $customers = $this->engine->getTable(
            'SELECT * FROM customers'
        );

        $this->assertNotEmpty($customers);
        $this->assertArrayHasKey('id', $customers[0]);
        $this->assertArrayHasKey('name', $customers[0]);
    }

    public function testGetRow()
    {
        $customer = $this->engine->getRow(
            'SELECT * FROM customers WHERE id = :id',
            ['id' => 1]
        );

        $this->assertNotNull($customer);
        $this->assertArrayHasKey('id', $customer);
        $this->assertSame(1, $customer['id']);
    }

    public function testGetCol()
    {
        $names = $this->engine->getCol('SELECT name FROM customers');

        $this->assertNotEmpty($names);
        $this->assertContains('Acme Corp', $names);
    }

    public function testGetValue()
    {
        $name = $this->engine->getValue(
            'SELECT name FROM customers WHERE id = :id',
            ['id' => 3]
        );

        $this->assertNotNull($name);
        $this->assertSame('Jane Doe', $name);
    }
}
