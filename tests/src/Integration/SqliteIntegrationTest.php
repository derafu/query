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

use Derafu\Query\Builder\SqlQueryBuilder;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SqlQueryBuilder::class)]
class SqliteIntegrationTest extends TestCase
{
    private PDO $db;

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
    }

    #[DataProvider('queryProvider')]
    public function testSqlQueries(
        string $description,
        string $sql,
        array $parameters
    ): void {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($parameters);
        $actual = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // For now, use same query for expected.
        $stmt = $this->db->prepare($sql);
        $stmt->execute($parameters);
        $expected = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->assertEquals($expected, $actual, $description);
    }

    public static function queryProvider(): array
    {
        $cases = require __DIR__ . '/../../fixtures/integration/queries_integration.php';
        $data = [];

        foreach ($cases['cases'] as $name => $case) {
            $data[$name] = [
                $case['description'],
                $case['sql'],
                $case['parameters']
            ];
        }

        return $data;
    }
}
