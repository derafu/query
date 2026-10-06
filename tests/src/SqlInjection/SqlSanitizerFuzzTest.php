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
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Fuzzing of the SQL sanitizer.
 *
 * The inputs are random but reproducible: they come from a generator with a
 * seed, which is fixed by default so every run is the same. To explore with
 * other inputs set `FUZZ_SEED` to a number, or to `random` for a new seed. The
 * seed is in the message of every assertion, so a failure can be repeated with
 * `FUZZ_SEED=<seed>`.
 */
#[CoversTrait(SqlSanitizerTrait::class)]
class SqlSanitizerFuzzTest extends TestCase
{
    private const FUZZ_ITERATIONS = 1000;

    private const DEFAULT_SEED = 19860102;

    private $sanitizer;

    private int $seed;

    private Randomizer $randomizer;

    protected function setUp(): void
    {
        $seed = getenv('FUZZ_SEED');
        $this->seed = match (true) {
            $seed === 'random' => random_int(1, PHP_INT_MAX),
            $seed !== false && ctype_digit($seed) => (int) $seed,
            default => self::DEFAULT_SEED,
        };
        $this->randomizer = new Randomizer(new Mt19937($this->seed));

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
            $this->assertMatchesRegularExpression('/^[a-zA-Z0-9_]*$/', $output, $this->context($input));
        }
    }

    public function testFuzzQualifiedIdentifiers(): void
    {
        for ($i = 0; $i < self::FUZZ_ITERATIONS; $i++) {
            $parts = [];
            $numParts = $this->randomizer->getInt(2, 4);

            for ($j = 0; $j < $numParts; $j++) {
                $parts[] = $this->generateRandomString();
            }

            $input = implode('.', $parts);
            $output = $this->sanitizer->sanitizeSimple($input);

            // Qualified identifiers should be dot-separated alphanumeric identifiers.
            $this->assertMatchesRegularExpression('/^[a-zA-Z0-9_]+(\.[a-zA-Z0-9_]+)*$/', $output, $this->context($input));
        }
    }

    public function testFuzzSqlFunctions(): void
    {
        $functions = ['COUNT', 'SUM', 'AVG', 'MAX', 'MIN', 'COALESCE', 'LENGTH'];

        for ($i = 0; $i < self::FUZZ_ITERATIONS; $i++) {
            $function = $functions[$this->randomizer->getInt(0, count($functions) - 1)];
            $numArgs = $this->randomizer->getInt(1, 3);
            $args = [];

            for ($j = 0; $j < $numArgs; $j++) {
                // Mix of identifiers and literal values.
                if ($this->randomizer->getInt(0, 1)) {
                    $args[] = $this->generateRandomString();
                } else {
                    $args[] = $this->randomizer->getInt(1, 1000);
                }
            }

            // Sometimes use * as an argument.
            if ($this->randomizer->getInt(0, 10) > 8) {
                $args = ['*'];
            }

            $input = $function . '(' . implode(', ', $args) . ')';
            $output = $this->sanitizer->sanitize($input);

            // Make sure function name is preserved and sanitized.
            $this->assertStringContainsString(preg_replace('/[^a-zA-Z0-9_]/', '', $function), $output, $this->context($input));

            // If using *, make sure it's preserved.
            if ($args === ['*']) {
                $this->assertStringContainsString('(*)', $output, $this->context($input));
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

    /**
     * Message for the assertions: with the seed, a failure can be repeated.
     */
    private function context(string $input): string
    {
        return sprintf('FUZZ_SEED=%d, input: %s', $this->seed, $input);
    }

    private function generateRandomString(int $length = 10): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-_. ;:\'"`~!@#$%^&*()+=[]{}\\|<>,/?';
        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[$this->randomizer->getInt(0, strlen($characters) - 1)];
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
