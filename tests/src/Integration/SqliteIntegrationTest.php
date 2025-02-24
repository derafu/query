<?php

declare(strict_types=1);

/**
 * Derafu: Query - Smart Query Builder.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsQuery\Integration;

use Derafu\Query\Builder\Contract\QueryBuilderInterface;
use Derafu\Query\Builder\Sql\SqlBuilderWhere;
use Derafu\Query\Builder\Sql\SqlQuery;
use Derafu\Query\Builder\SqlQueryBuilder;
use Derafu\Query\Engine\Contract\SqlEngineInterface;
use Derafu\Query\Engine\SqlEngine;
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
#[CoversClass(SqlEngine::class)]
class SqliteIntegrationTest extends TestCase
{
    private SqlEngineInterface $engine;

    private QueryBuilderInterface $query;

    protected function setUp(): void
    {
        // Create in-memory SQLite database and load schema and test data.
        $this->engine = new SqlEngine(new PDO('sqlite::memory:'));
        $schema = file_get_contents(__DIR__ . '/../../fixtures/integration/billing_schema.sql');
        $this->engine->getConnection()->exec($schema);
        $data = file_get_contents(__DIR__ . '/../../fixtures/integration/billing_data.sql');
        $this->engine->getConnection()->exec($data);

        // Create query builder.
        $pathParser = new PathParser();
        $loader = new OperatorLoader();
        $operators = $loader->loadFromFile(__DIR__ . '/../../../config/operators.yaml');
        $manager = new OperatorManager($operators);
        $filterParser = new FilterParser($manager);
        $expressionParser = new ExpressionParser($pathParser, $filterParser);
        $this->query = new SqlQueryBuilder($this->engine, $expressionParser);
    }

    #[DataProvider('queryProvider')]
    public function testSqlQueries(
        string $description,
        array $rawSql,
        array $smartQuery
    ): void {
        // Execute raw SQL.
        $expected = $this->engine->execute($rawSql['sql'], $rawSql['parameters']);

        // Build query using our fluent builder
        $builder = $this->buildQueryFromFixture($smartQuery);

        // Execute and compare results
        $actual = $builder->execute();

        $this->assertSame($expected, $actual, $description);
    }

    /**
     * Builds a query from the fixture structure.
     *
     * This method handles complex query structures including nested conditions.
     *
     * @param array $fixture The fixture structure defining the query.
     * @return QueryBuilderInterface The constructed query builder.
     */
    private function buildQueryFromFixture(array $fixture): QueryBuilderInterface
    {
        // Start with the table
        $builder = $this->query->table($fixture['table']);

        // Process basic where condition
        if (isset($fixture['where'])) {
            $builder->where($fixture['where']);
        }

        // Process additional conditions
        $this->processAdditionalConditions($builder, $fixture);

        return $builder;
    }

    /**
     * Processes additional conditions based on fixture keys.
     *
     * @param QueryBuilderInterface $builder The query builder to modify.
     * @param array $fixture The fixture to process.
     */
    private function processAdditionalConditions(
        QueryBuilderInterface $builder,
        array $fixture
    ): void {
        // Process simple additional conditions.
        if (isset($fixture['andWhere'])) {
            $builder->andWhere($fixture['andWhere']);
        }

        if (isset($fixture['orWhere'])) {
            $builder->orWhere($fixture['orWhere']);
        }

        if (isset($fixture['andWhereOr'])) {
            $builder->andWhereOr($fixture['andWhereOr']);
        }

        // Process advanced/multi-step conditions.
        if (isset($fixture['multiStep'])) {
            foreach ($fixture['multiStep'] as $step) {
                $method = key($step);
                $condition = $step[$method];

                if ($method === 'orWhere') {
                    $builder->orWhere($condition);
                } else {
                    $builder->$method($condition);
                }
            }
        }
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
}
