<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsQuery\Functional\Filter;

use Derafu\Query\Filter\Contract\FilterInterface;
use Derafu\Query\Filter\Contract\FilterParserInterface;
use Derafu\Query\Filter\Filter;
use Derafu\Query\Filter\FilterParser;
use Derafu\Query\Operator\Operator;
use Derafu\Query\Operator\OperatorLoader;
use Derafu\Query\Operator\OperatorManager;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the operator parser.
 *
 * This test ensures the parser can correctly identify operators and their
 * values from input strings, handling both valid and invalid cases.
 */
#[CoversClass(FilterParser::class)]
#[CoversClass(Filter::class)]
#[CoversClass(OperatorLoader::class)]
#[CoversClass(Operator::class)]
#[CoversClass(OperatorManager::class)]
final class FilterParserTest extends TestCase
{
    /**
     * Instance of the parser being tested.
     */
    private FilterParserInterface $parser;

    /**
     * Sets up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $loader = new OperatorLoader();
        $operators = $loader->loadFromFile(__DIR__ . '/../../../../resources/operators.yaml');
        $manager = new OperatorManager($operators);
        $this->parser = new FilterParser($manager);
    }

    /**
     * Data provider for parser tests.
     *
     * Provides test cases for both valid and invalid operator expressions.
     *
     * @return array<string,array{string,string}> Test cases.
     */
    public static function parserCasesProvider(): array
    {
        $filters = require __DIR__ . '/../../../fixtures/functional/filters.php';

        $cases = [];

        // Process valid cases.
        foreach ($filters['OK'] as $expression) {
            $cases['Valid: ' . $expression] = [$expression, 'valid'];
        }

        // Process invalid cases.
        foreach ($filters['FAIL'] as $expression) {
            $cases['Invalid: ' . $expression] = [$expression, 'invalid'];
        }

        return $cases;
    }

    /**
     * Tests operator parsing with various expressions.
     *
     * @param string $expression The operator expression to test.
     * @param string $expectation Whether the case should be valid or invalid.
     */
    #[DataProvider('parserCasesProvider')]
    public function testOperatorParsing(
        string $expression,
        string $expectation
    ): void {
        if ($expectation === 'invalid') {
            $this->expectException(InvalidArgumentException::class);
            $this->parser->parse($expression);
        } else {
            $filter = $this->parser->parse($expression);
            $this->assertInstanceOf(FilterInterface::class, $filter);
        }
    }
}
