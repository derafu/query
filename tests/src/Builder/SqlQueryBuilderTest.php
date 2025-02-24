<?php

declare(strict_types=1);

/**
 * Derafu: Query - Smart Query Builder.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsQuery\Builder;

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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SqlQueryBuilder::class)]
#[CoversClass(SqlQuery::class)]
#[CoversClass(Filter::class)]
#[CoversClass(Path::class)]
#[CoversClass(Segment::class)]
#[CoversClass(Operator::class)]
#[CoversClass(OperatorLoader::class)]
#[CoversClass(OperatorManager::class)]
#[CoversClass(FilterParser::class)]
#[CoversClass(PathParser::class)]
class SqlQueryBuilderTest extends TestCase
{
    private PathParserInterface $pathParser;

    private FilterParserInterface $filterParser;

    protected function setUp(): void
    {
        $this->pathParser = new PathParser();
        $loader = new OperatorLoader();
        $operators = $loader->loadFromFile(__DIR__ . '/../../../config/operators.yaml');
        $manager = new OperatorManager($operators);
        $this->filterParser = new FilterParser($manager);
    }

    #[DataProvider('queryCasesProvider')]
    public function testBuildQuery(
        string $pathExpression,
        string $filterExpression,
        array $expected,
        string $engine
    ): void {
        // Create the builder.
        $builder = new SqlQueryBuilder($engine);

        // Parse path and filter.
        $path = $this->pathParser->parse($pathExpression);
        $filter = $this->filterParser->parse($filterExpression);

        // Build query
        $query = $builder->build($path, $filter);
        $result = $query->getQuery();

        // Replace generated unique IDs with placeholder for comparison.
        $result = $this->normalizeQueryForComparison($result);
        $expected = $this->normalizeQueryForComparison($expected);

        $this->assertSame($expected, $result);
    }

    public static function queryCasesProvider(): array
    {
        $cases = require __DIR__ . '/../../fixtures/queries_sql.php';
        $testCases = [];

        foreach ($cases['cases'] as $case) {
            $testCases["Path: {$case['path']}, Filter: {$case['filter']}"] = [
                'pathExpression' => $case['path'],
                'filterExpression' => $case['filter'],
                'expected' => $case['expected'],
                'engine' => $case['engine'] ?? 'pgsql',
            ];
        }

        return $testCases;
    }

    private function normalizeQueryForComparison(array $queryData): array
    {
        // Pattern to match any param_ followed by anything until a space or quote.
        $pattern = '/param_[^\'"\s]+/';

        // Replace actual unique IDs with predictable placeholder.
        $sql = preg_replace($pattern, 'param_{id}', $queryData['sql']);
        $parameters = array_combine(
            array_map(
                fn ($key) => preg_replace($pattern, 'param_{id}', $key),
                array_keys($queryData['parameters'])
            ),
            array_values($queryData['parameters'])
        );

        return [
            'sql' => $sql,
            'parameters' => $parameters,
        ];
    }
}
