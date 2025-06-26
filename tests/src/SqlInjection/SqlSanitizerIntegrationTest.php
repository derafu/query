<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsQuery\SqlInjection;

use Derafu\Query\Builder\Sql\SqlQuery;
use Derafu\Query\Builder\SqlQueryBuilder;
use Derafu\Query\Engine\PdoEngine;
use Derafu\Query\Filter\ExpressionParser;
use Derafu\Query\Filter\FilterParser;
use Derafu\Query\Filter\PathParser;
use Derafu\Query\Operator\Operator;
use Derafu\Query\Operator\OperatorLoader;
use Derafu\Query\Operator\OperatorManager;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SqlQueryBuilder::class)]
#[CoversClass(SqlQuery::class)]
#[CoversClass(PdoEngine::class)]
#[CoversClass(ExpressionParser::class)]
#[CoversClass(FilterParser::class)]
#[CoversClass(Operator::class)]
#[CoversClass(OperatorLoader::class)]
#[CoversClass(OperatorManager::class)]
class SqlSanitizerIntegrationTest extends TestCase
{
    private SqlQueryBuilder $queryBuilder;

    private PDO $pdo;

    protected function setUp(): void
    {
        // Create in-memory SQLite database.
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create test table.
        $this->pdo->exec('
            CREATE TABLE test_table (
                id INTEGER PRIMARY KEY,
                name TEXT,
                value NUMERIC,
                status TEXT
            )
        ');

        // Insert test data.
        $this->pdo->exec("
            INSERT INTO test_table (id, name, value, status) VALUES
            (1, 'Item 1', 100, 'active'),
            (2, 'Item 2', 200, 'inactive'),
            (3, 'Item 3', 300, 'active')
        ");

        // Setup the query builder with all dependencies.
        $engine = new PdoEngine($this->pdo);
        $pathParser = new PathParser();
        $loader = new OperatorLoader();
        $operators = $loader->loadFromFile(__DIR__ . '/../../../resources/operators.yaml');
        $manager = new OperatorManager($operators);
        $filterParser = new FilterParser($manager);
        $expressionParser = new ExpressionParser($pathParser, $filterParser);

        $this->queryBuilder = new SqlQueryBuilder($engine, $expressionParser);
    }

    /**
     * Test simple SELECT with column list.
     */
    public function testSimpleSelect(): void
    {
        $result = $this->queryBuilder
            ->table('test_table')
            ->select('id, name, value')
            ->execute();

        $this->assertCount(3, $result);
        $this->assertSame('Item 1', $result[0]['name']);
    }

    /**
     * Test SELECT with function and alias.
     */
    public function testFunctionSelect(): void
    {
        $result = $this->queryBuilder
            ->table('test_table')
            ->select('COUNT(*) AS total')
            ->execute();

        $this->assertSame(3, $result[0]['total']);
    }

    /**
     * Test multiple aggregate functions.
     */
    public function testMultipleFunctions(): void
    {
        $result = $this->queryBuilder
            ->table('test_table')
            ->select('COUNT(*) AS count, AVG(value) AS average, SUM(value) AS total')
            ->execute();

        $this->assertSame(3, $result[0]['count']);
        $this->assertSame(200.0, $result[0]['average']);
        $this->assertSame(600, $result[0]['total']);
    }

    /**
     * Test GROUP BY with functions.
     */
    public function testGroupByWithFunctions(): void
    {
        $result = $this->queryBuilder
            ->table('test_table')
            ->select('status, COUNT(*) AS count, SUM(value) AS total')
            ->groupBy('status')
            ->execute();

        $this->assertCount(2, $result);

        // Find the active status row.
        $activeRow = null;
        foreach ($result as $row) {
            if ($row['status'] === 'active') {
                $activeRow = $row;
                break;
            }
        }

        $this->assertNotNull($activeRow);
        $this->assertSame(2, $activeRow['count']);
        $this->assertSame(400, $activeRow['total']);
    }

    /**
     * Test query with potentially dangerous input.
     */
    public function testPotentiallyDangerousInput(): void
    {
        // This would be dangerous if not sanitized.
        $dangerousInput = "status, (SELECT password FROM users) AS hacked";

        // The query should execute with error, because the dangerous part
        // should be kept sanitized.
        $this->expectException(PDOException::class);

        // This should sanitize the input and prevent SQL injection.
        $result = $this->queryBuilder
            ->table('test_table')
            ->select($dangerousInput)
            ->execute();
    }
}
