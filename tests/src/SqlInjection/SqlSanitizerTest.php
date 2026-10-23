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

use Derafu\Query\Builder\Sql\SqlSanitizerTrait;
use Derafu\TestsQuery\Fixture\SqlSanitizer;
use Derafu\Translation\Exception\Core\TranslatableRuntimeException;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../fixtures/SqlSanitizer.php';

#[CoversTrait(SqlSanitizerTrait::class)]
class SqlSanitizerTest extends TestCase
{
    private SqlSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new SqlSanitizer();
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

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testPcreFailureThrowsBeforeCallingTheSanitizerCallback(): void
    {
        $jit = ini_get('pcre.jit');
        $limit = ini_get('pcre.backtrack_limit');
        $this->assertIsString($jit);
        $this->assertIsString($limit);
        $callbackInvoked = false;
        $this->sanitizer->setSanitizeCallback(function (string $identifier) use (&$callbackInvoked): string {
            $callbackInvoked = true;

            return $identifier;
        });
        $this->expectException(TranslatableRuntimeException::class);
        $this->expectExceptionMessage('Failed to sanitize SQL identifier: Backtrack limit exhausted');

        try {
            // A fresh process prevents an already JIT-compiled pattern from
            // bypassing the interpreter's backtracking limit.
            ini_set('pcre.jit', '0');
            ini_set('pcre.backtrack_limit', '0');
            $this->sanitizer->sanitizeSimple('invalid-name');
        } finally {
            ini_set('pcre.backtrack_limit', $limit);
            ini_set('pcre.jit', $jit);
            $this->assertFalse($callbackInvoked);
        }
    }

    /**
     * @return array<string,array{string,string}>
     */
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

    /**
     * @return array<string,array{string,string}>
     */
    public static function provideQualifiedIdentifiers(): array
    {
        return [
            'Table and column' => ['table.column', 'table.column'],
            'Schema table column' => ['schema.table.column', 'schema.table.column'],
            'With invalid chars' => ['table-name.column-name', 'tablenamecolumnname'],
        ];
    }

    /**
     * @return array<string,array{string,string}>
     */
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

    /**
     * @return array<string,array{string,string}>
     */
    public static function provideAliasingExpressions(): array
    {
        return [
            'Simple alias' => ['column AS alias', 'column AS alias'],
            'Function with alias' => ['COUNT(*) AS total', 'COUNT(*) AS total'],
            'With quotes in alias' => ['column AS "my alias"', 'column AS myalias'],
            'Complex with alias' => ['SUM(price * quantity) AS total', 'SUM(price * quantity) AS total'],
        ];
    }

    /**
     * @return array<string,array{string,string}>
     */
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
