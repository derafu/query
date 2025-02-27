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
use PHPUnit\Framework\TestCase;

#[CoversClass(SqlSanitizerTrait::class)]
class SqlSanitizerFuzzTest extends TestCase
{
    private $sanitizer;

    private const FUZZ_ITERATIONS = 1000;

    protected function setUp(): void
    {
        $this->sanitizer = new class () {
            use SqlSanitizerTrait;

            public function sanitize(string $expression): string
            {
                return $this->sanitizeSqlIdentifier($expression);
            }

            public function sanitizeSimple(string $expression): string
            {
                return $this->sanitizeSqlSimpleIdentifier($expression);
            }
        };
    }

    public function testFuzzSimpleIdentifiers(): void
    {
        for ($i = 0; $i < self::FUZZ_ITERATIONS; $i++) {
            $input = $this->generateRandomString();
            $output = $this->sanitizer->sanitizeSimple($input);

            // Simple identifiers should only contain alphanumeric and underscore.
            $this->assertMatchesRegularExpression('/^[a-zA-Z0-9_]*$/', $output);
        }
    }

    public function testFuzzQualifiedIdentifiers(): void
    {
        for ($i = 0; $i < self::FUZZ_ITERATIONS; $i++) {
            $parts = [];
            $numParts = random_int(2, 4);

            for ($j = 0; $j < $numParts; $j++) {
                $parts[] = $this->generateRandomString();
            }

            $input = implode('.', $parts);
            $output = $this->sanitizer->sanitizeSimple($input);

            // Qualified identifiers should be dot-separated alphanumeric identifiers.
            $this->assertMatchesRegularExpression('/^[a-zA-Z0-9_]+(\.[a-zA-Z0-9_]+)*$/', $output);
        }
    }

    public function testFuzzSqlFunctions(): void
    {
        $functions = ['COUNT', 'SUM', 'AVG', 'MAX', 'MIN', 'COALESCE', 'LENGTH'];

        for ($i = 0; $i < self::FUZZ_ITERATIONS; $i++) {
            $function = $functions[array_rand($functions)];
            $numArgs = random_int(1, 3);
            $args = [];

            for ($j = 0; $j < $numArgs; $j++) {
                // Mix of identifiers and literal values.
                if (random_int(0, 1)) {
                    $args[] = $this->generateRandomString();
                } else {
                    $args[] = random_int(1, 1000);
                }
            }

            // Sometimes use * as an argument.
            if (random_int(0, 10) > 8) {
                $args = ['*'];
            }

            $input = $function . '(' . implode(', ', $args) . ')';
            $output = $this->sanitizer->sanitize($input);

            // Make sure function name is preserved and sanitized.
            $this->assertStringContainsString(preg_replace('/[^a-zA-Z0-9_]/', '', $function), $output);

            // If using *, make sure it's preserved.
            if ($args === ['*']) {
                $this->assertStringContainsString('(*)', $output);
            }
        }
    }

    public function testFuzzSqlInjections(): void
    {
        $injectionTemplates = [
            "' OR '1'='1",
            "; DROP TABLE users;",
            "-- comment",
            "/**/UNION SELECT/**/'",
            "column`; DELETE FROM users; --",
            "' OR 1=1 --",
            "')) OR 1=1--",
            "' UNION ALL SELECT NULL, NULL, NULL, NULL--",
            "admin'--",
            "`; INSERT INTO users VALUES ('hacker', 'password')`",
        ];

        for ($i = 0; $i < count($injectionTemplates); $i++) {
            $injection = $injectionTemplates[$i];

            // Try direct injection.
            $output = $this->sanitizer->sanitize($injection);
            $this->assertIsSafe($output);

            // Try in different contexts.
            $output = $this->sanitizer->sanitize("column " . $injection);
            $this->assertIsSafe($output);

            $output = $this->sanitizer->sanitize("column AS " . $injection);
            $this->assertIsSafe($output);

            // Try with escaping mechanisms.
            $output = $this->sanitizer->sanitize(str_replace("'", "\'", $injection));
            $this->assertIsSafe($output);
        }
    }

    private function generateRandomString(int $length = 10): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-_. ;:\'"`~!@#$%^&*()+=[]{}\\|<>,/?';
        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $randomString;
    }

    private function assertIsSafe(string $output): void
    {
        // Search for complete SQL patterns with spaces.
        $this->assertTrue(preg_match('/DELETE\s+FROM/i', $output) === 0);
        $this->assertTrue(preg_match('/DROP\s+TABLE/i', $output) === 0);
        $this->assertTrue(preg_match('/INSERT\s+INTO/i', $output) === 0);
        $this->assertTrue(preg_match('/UNION\s+(?:ALL\s+)?SELECT/i', $output) === 0);

        // Do not allow semicolons, comments, etc.
        $this->assertFalse(str_contains($output, ';'));
        $this->assertFalse(str_contains($output, '--'));
        $this->assertFalse(str_contains($output, '/*') && str_contains($output, '*/'));

        // Do not allow full comparison expressions.
        $this->assertTrue(preg_match('/=\s*1/i', $output) === 0);

        // Do not allow quotes.
        $this->assertFalse(str_contains($output, "'") || str_contains($output, '"'));
    }
}
