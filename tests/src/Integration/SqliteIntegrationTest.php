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

use Derafu\Query\Builder\SqlQuery;
use Derafu\Query\Builder\SqlQueryBuilder;
use Derafu\Query\Filter\Contract\FilterParserInterface;
use Derafu\Query\Filter\Contract\PathParserInterface;
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
#[CoversClass(SqlQuery::class)]
#[CoversClass(Filter::class)]
#[CoversClass(FilterParser::class)]
#[CoversClass(Path::class)]
#[CoversClass(PathParser::class)]
#[CoversClass(Segment::class)]
#[CoversClass(Operator::class)]
#[CoversClass(OperatorLoader::class)]
#[CoversClass(OperatorManager::class)]
class SqliteIntegrationTest extends TestCase
{
    private PDO $db;

    private PathParserInterface $pathParser;

    private FilterParserInterface $filterParser;

    protected function setUp(): void
    {
        // Create in-memory SQLite database.
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Load schema.
        $schema = file_get_contents(__DIR__ . '/../../fixtures/integration/billing_schema.sql');
        $this->db->exec($schema);

        // Load test data.
        $data = file_get_contents(__DIR__ . '/../../fixtures/integration/billing_data.sql');
        $this->db->exec($data);

        // Create parser.
        $this->pathParser = new PathParser();
        $loader = new OperatorLoader();
        $operators = $loader->loadFromFile(__DIR__ . '/../../../config/operators.yaml');
        $manager = new OperatorManager($operators);
        $this->filterParser = new FilterParser($manager);
    }

    #[DataProvider('queryProvider')]
    public function testSqlQueries(
        string $description,
        array $rawSql,
        array $smartQuery
    ): void {
        // Execute raw SQL.
        $stmt = $this->db->prepare($rawSql['sql']);
        $stmt->execute($rawSql['parameters']);
        $expected = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Execute using Derafu\Query\Builder.
        $actual = $this->executeWithBuilder(
            $smartQuery['table'],
            $smartQuery['path'],
            $smartQuery['filter']
        );

        $this->assertSame($expected, $actual, $description);
    }

    private function executeWithBuilder(
        string $table,
        string $path,
        string $filter
    ): array {
        // Parse path and filter.
        $path = $this->pathParser->parse($path);
        $filter = $this->filterParser->parse($filter);

        // Build query.
        $builder = new SqlQueryBuilder('sqlite');
        $query = $builder->build($path, $filter);
        $result = $query->getQuery();

        // Add the FROM clause to the SQL.
        $sql = 'SELECT * FROM ' . $table . ' WHERE ' . $result['sql'];

        // Execute.
        $stmt = $this->db->prepare($sql);
        $stmt->execute($result['parameters']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function queryProvider(): array
    {
        $cases = require __DIR__ . '/../../fixtures/integration/queries_integration.php';
        $data = [];

        foreach ($cases['cases'] as $name => $case) {
            $data[$name] = [
                $case['description'],
                $case['raw_sql'],
                $case['smart_query'],
            ];
        }

        return $data;
    }
}
