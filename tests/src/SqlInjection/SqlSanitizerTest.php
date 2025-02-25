<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsQuery\SqlInjection;

use Derafu\Query\Builder\Sql\SqlSanitizerTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SqlSanitizerTrait::class)]
class SqlSanitizerTest extends TestCase
{
    private $sanitizer;

    protected function setUp(): void
    {
        // Create a concrete implementation of the trait for testing.
        $this->sanitizer = new class () {
            use SqlSanitizerTrait;

            // Make methods public for testing.
            public function sanitize(string $expression): string
            {
                return $this->sanitizeSqlIdentifier($expression);
            }

            public function setSanitizeCallback(callable $callback): void
            {
                $this->setSanitizeSqlIdentifierCallback($callback);
            }
        };
    }

    #[DataProvider('provideSimpleIdentifiers')]
    public function testSimpleIdentifiers(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->sanitizer->sanitize($input));
    }

    #[DataProvider('provideQualifiedIdentifiers')]
    public function testQualifiedIdentifiers(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->sanitizer->sanitize($input));
    }

    #[DataProvider('provideAggregateFunctions')]
    public function testAggregateFunctions(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->sanitizer->sanitize($input));
    }

    #[DataProvider('provideAliasingExpressions')]
    public function testAliasingExpressions(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->sanitizer->sanitize($input));
    }

    #[DataProvider('providePotentialSqlInjections')]
    public function testPotentialSqlInjections(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->sanitizer->sanitize($input));
    }

    public function testSanitizeCallbackUsage(): void
    {
        // Set a custom callback.
        $this->sanitizer->setSanitizeCallback(fn (string $identifier) => "`{$identifier}`");

        $this->assertSame("`column_name`", $this->sanitizer->sanitize("column_name"));
        $this->assertSame("COUNT(*)", $this->sanitizer->sanitize("COUNT(*)"));
        $this->assertSame("SUM(`price`)", $this->sanitizer->sanitize("SUM(price)"));
        $this->assertSame("`table`.`column`", $this->sanitizer->sanitize("table.column"));
    }

    public static function provideSimpleIdentifiers(): array
    {
        return [
            'Simple column name' => ['column_name', 'column_name'],
            'Column with invalid chars' => ['column-name', 'columnname'],
            'Column with numbers' => ['column123', 'column123'],
            'Asterisk' => ['*', '*'],
            'Empty string' => ['', ''],
        ];
    }

    public static function provideQualifiedIdentifiers(): array
    {
        return [
            'Table and column' => ['table.column', 'table.column'],
            'Schema table column' => ['schema.table.column', 'schema.table.column'],
            'With invalid chars' => ['table-name.column-name', 'tablenamecolumnname'],
        ];
    }

    public static function provideAggregateFunctions(): array
    {
        return [
            'COUNT star' => ['COUNT(*)', 'COUNT(*)'],
            'SUM' => ['SUM(price)', 'SUM(price)'],
            'AVG with spacing' => ['AVG( price )', 'AVG(price)'],
            'Multiple arguments' => ['COALESCE(price, 0)', 'COALESCE(price, 0)'],
            'Nested function' => ['MAX(LENGTH(name))', 'MAX(LENGTH(name))'],
        ];
    }

    public static function provideAliasingExpressions(): array
    {
        return [
            'Simple alias' => ['column AS alias', 'column AS alias'],
            'Function with alias' => ['COUNT(*) AS total', 'COUNT(*) AS total'],
            'With quotes in alias' => ['column AS "my alias"', 'column AS myalias'],
            'Complex with alias' => ['SUM(price * quantity) AS total', 'SUM(price * quantity) AS total'],
        ];
    }

    public static function providePotentialSqlInjections(): array
    {
        return [
            'SQL comment' => ['column--injection', 'columninjection'],
            'UNION attempt' => ['column UNION SELECT', 'columnUNIONSELECT'],
            'Quotation marks' => ["column' OR '1'='1", 'columnOR11'],
            'Semicolon' => ['column;DROP TABLE users', 'columnDROPTABLEusers'],
            'Multiple statements' => ['column; DELETE FROM users; --', 'columnDELETEFROMusers'],
        ];
    }
}
