<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsQuery\Functional\Builder;

use Derafu\Query\Builder\AliasScope;
use Derafu\Query\Builder\Sql\SqlBuilderWhere;
use Derafu\Query\Builder\Sql\SqlQuery;
use Derafu\Query\Filter\Condition;
use Derafu\Query\Filter\Filter;
use Derafu\Query\Filter\Path;
use Derafu\Query\Filter\PathParser;
use Derafu\Query\Filter\Segment;
use Derafu\Query\Operator\Operator;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[UsesClass(AliasScope::class)]
#[CoversClass(SqlBuilderWhere::class)]
#[UsesClass(SqlQuery::class)]
#[UsesClass(Condition::class)]
#[UsesClass(Filter::class)]
#[UsesClass(Path::class)]
#[UsesClass(PathParser::class)]
#[UsesClass(Segment::class)]
#[UsesClass(Operator::class)]
final class SqlValueCastingTest extends TestCase
{
    /**
     * @param array<array-key,string> $rules
     * @return array{sql:string,parameters:array<string,mixed>}
     */
    private function compile(
        ?string $value,
        array $rules = [],
        string $delimiter = ',',
        bool $literal = true,
        string $sql = '{{column}} = {{value}}'
    ): array {
        $operator = new Operator('=', ['sql' => $sql, 'cast' => $rules]);
        $condition = new Condition((new PathParser())->parse('p__id'), new Filter($operator, $value), $literal);

        return (new SqlBuilderWhere('sqlite', $delimiter))->build($condition)->getQuery();
    }

    public function testRejectsAnEmptyListDelimiter(): void
    {
        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('List delimiter cannot be empty.');
        new SqlBuilderWhere('sqlite', '');
    }

    /**
     * @return iterable<string,array{string}>
     */
    public static function rulesRequiringAString(): iterable
    {
        foreach (['like_start', 'like', 'like_end', 'list', 'date', 'month', 'year', 'period'] as $rule) {
            yield $rule => [$rule];
        }
    }

    #[DataProvider('rulesRequiringAString')]
    public function testRejectsAStringRuleAfterProducingAnArray(string $rule): void
    {
        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('Casting rule "' . $rule . '" requires a string');
        $this->compile('1,2', ['list', $rule]);
    }

    /**
     * @return iterable<string,array{string,array<array-key,string>,string}>
     */
    public static function stringCastingCases(): iterable
    {
        yield 'start wildcard' => ['abc', ['like_start'], 'abc%'];
        yield 'both wildcards' => ['abc', ['like'], '%abc%'];
        yield 'end wildcard' => ['abc', ['like_end'], '%abc'];
        yield 'combined string rules' => ['abc', ['like_start', 'like_end'], '%abc%'];
        yield 'full date' => ['20261022', ['date'], '2026-10-22'];
        yield 'short date' => ['261022', ['date'], '2026-10-22'];
        yield 'month' => ['8', ['month'], '08'];
        yield 'short year' => ['26', ['year'], '2026'];
        yield 'full year' => ['2026', ['year'], '2026'];
        yield 'full period' => ['202610', ['period'], '202610'];
        yield 'short period' => ['2610', ['period'], '202610'];
        yield 'unknown rule' => ['abc', ['custom'], 'abc'];
    }

    /**
     * @param array<array-key,string> $rules
     */
    #[DataProvider('stringCastingCases')]
    public function testStringRulesPreserveTheirResults(string $value, array $rules, string $expected): void
    {
        $query = $this->compile($value, $rules);
        $this->assertSame([$expected], array_values($query['parameters']));
    }

    public function testNullRemainsNullWithoutApplyingStringRules(): void
    {
        $query = $this->compile(null, ['list', 'like']);
        $this->assertSame([null], array_values($query['parameters']));
    }

    public function testListRulesPreserveOrderAndAllowMulticharacterDelimiters(): void
    {
        $query = $this->compile('1::2::3', ['list', 'custom'], '::', true, '{{column}} IN ({{values}})');
        $this->assertSame(['1', '2', '3'], array_values($query['parameters']));
    }

    public function testStringRulesCanPrecedeTheListRule(): void
    {
        $query = $this->compile('a,b', ['prefix' => 'like', 'split' => 'list'], ',', true, '{{column}} IN ({{values}})');
        $this->assertSame(['%a', 'b%'], array_values($query['parameters']));
    }

    public function testNonLiteralNullIsRejectedWhenTheTemplateNeedsAnIdentifier(): void
    {
        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('"value" must be of type string');
        $this->compile(null, [], ',', false);
    }

    public function testNonLiteralNullIsAllowedWhenNoIdentifierValueIsNeeded(): void
    {
        $query = $this->compile(null, [], ',', false, '{{column}} IS NULL');
        $this->assertSame('p.id IS NULL', $query['sql']);
        $this->assertSame([], $query['parameters']);
    }

    public function testNonLiteralColumnComparisonExecutesWithoutParameters(): void
    {
        $query = $this->compile('p.price', [], ',', false, '{{column}} < {{value}}');
        $this->assertSame('p.id < p.price', $query['sql']);
        $this->assertSame([], $query['parameters']);

        $pdo = new PDO('sqlite::memory:');
        $fixture = file_get_contents(__DIR__ . '/../../../fixtures/functional/query_config.sql');
        $this->assertIsString($fixture);
        $pdo->exec($fixture);
        $statement = $pdo->query('SELECT p.id FROM products p WHERE ' . $query['sql'] . ' ORDER BY p.id');
        $this->assertNotFalse($statement);
        $this->assertSame([1, 2, 3], $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * @return iterable<string,array{string,?string,string}>
     */
    public static function countComparisons(): iterable
    {
        yield 'zero with whitespace' => ['=', ' 0 ', 'NOT EXISTS'];
        yield 'one with whitespace' => ['>=', ' 1 ', 'EXISTS'];
        yield 'empty with null' => ['is:empty', null, 'NOT EXISTS'];
        yield 'not empty with null' => ['isnot:empty', null, 'EXISTS'];
    }

    #[DataProvider('countComparisons')]
    public function testCountOptimizationPreservesNullAndWhitespace(string $symbol, ?string $value, string $prefix): void
    {
        $condition = new Condition(
            (new PathParser())->parse('___stock[on:id=product_id]__COUNT(*)'),
            new Filter(new Operator($symbol, []), $value)
        );
        $query = (new SqlBuilderWhere('sqlite'))->build($condition, 'p')->getQuery();
        $this->assertStringStartsWith($prefix . ' (SELECT 1', $query['sql']);
        $this->assertSame([], $query['parameters']);
    }
}
