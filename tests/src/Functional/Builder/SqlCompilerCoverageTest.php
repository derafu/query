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
use Derafu\Translation\Exception\Runtime\TranslatableUnderflowException;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AliasScope::class)]
#[CoversClass(SqlBuilderWhere::class)]
#[UsesClass(SqlQuery::class)]
#[UsesClass(Condition::class)]
#[UsesClass(Filter::class)]
#[UsesClass(Path::class)]
#[UsesClass(PathParser::class)]
#[UsesClass(Segment::class)]
#[UsesClass(Operator::class)]
final class SqlCompilerCoverageTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        foreach (['billing_schema.sql', 'billing_data.sql'] as $fixture) {
            $sql = file_get_contents(__DIR__ . '/../../../fixtures/integration/' . $fixture);
            $this->assertIsString($sql);
            $this->pdo->exec($sql);
        }
    }

    /**
     * @return list<int>
     */
    private function execute(Condition $condition, string $table, string $alias): array
    {
        $query = (new SqlBuilderWhere('sqlite'))->build($condition, $alias)->getQuery();
        $statement = $this->pdo->prepare('SELECT ' . $alias . '.id FROM ' . $table . ' ' . $alias . ' WHERE ' . $query['sql'] . ' ORDER BY ' . $alias . '.id');
        $this->assertNotFalse($statement);
        $statement->execute($query['parameters']);
        $ids = [];
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $this->assertIsInt($id);
            $ids[] = $id;
        }

        return $ids;
    }

    /**
     * @return iterable<string,array{string,string,list<int>}>
     */
    public static function qualifiedFunctions(): iterable
    {
        yield 'qualified column' => ['LENGTH(name)', '14', [1, 3, 8]];
        yield 'numeric argument' => ['COALESCE(price, 0)', '500', [1, 2]];
        yield 'null constant' => ['COALESCE(NULL, price)', '500', [1, 2]];
        yield 'true constant' => ['IIF(TRUE, price, 0)', '500', [1, 2]];
        yield 'false constant' => ['IIF(FALSE, 0, price)', '500', [1, 2]];
        yield 'string literal' => ["INSTR(name, 'o')", '0', [1, 2, 3, 5, 6, 7, 8]];
        yield 'quoted identifier' => ['COALESCE("price", 0)', '500', [1, 2]];
        yield 'parenthesized arithmetic' => ['ABS((price - 800))', '500', [3, 4, 5, 6, 7]];
        yield 'zero without surrounding whitespace' => ['COALESCE(price,0)', '500', [1, 2]];
        yield 'single zero argument' => ['ABS(0)', '-1', [1, 2, 3, 4, 5, 6, 7, 8]];
        yield 'nested function and trailing zero' => ['COALESCE(ROUND(price,0),0)', '500', [1, 2]];
        yield 'nested zero-argument function' => ['LENGTH(datetime())', '0', [1, 2, 3, 4, 5, 6, 7, 8]];
    }

    /**
     * @param list<int> $expectedIds
     */
    #[DataProvider('qualifiedFunctions')]
    public function testQualifiedFunctionsExecuteAgainstRealColumns(string $function, string $value, array $expectedIds): void
    {
        $operator = new Operator('>', ['sql' => '{{column}} > CAST({{value}} AS INTEGER)']);
        $condition = new Condition(
            (new PathParser())->parse('products[alias:p]__' . $function),
            new Filter($operator, $value)
        );
        $this->assertSame($expectedIds, $this->execute($condition, 'products', 'p'));
    }

    public function testCommasInsideFunctionStringLiteralsArePreserved(): void
    {
        $this->pdo->exec("UPDATE products SET name = 'Laptop, Business' WHERE id = 1");
        $condition = new Condition(
            (new PathParser())->parse("products[alias:p]__INSTR(name, ',')"),
            new Filter(new Operator('>', ['sql' => '{{column}} > CAST({{value}} AS INTEGER)']), '0')
        );

        $this->assertSame([1], $this->execute($condition, 'products', 'p'));
    }

    /**
     * @return iterable<string,array{string}>
     */
    public static function nestedExistsPaths(): iterable
    {
        yield 'explicit aliases' => ['___invoices[alias:i,on:id=customer_id]___payments[alias:p,on:id=invoice_id]__status'];
        yield 'table names' => ['___invoices[on:id=customer_id]___payments[on:id=invoice_id]__status'];
    }

    #[DataProvider('nestedExistsPaths')]
    public function testNestedExistsJoinsKeepBothCorrelationConditions(string $path): void
    {
        $condition = new Condition(
            (new PathParser())->parse($path),
            new Filter(new Operator('=', ['sql' => '{{column}} = {{value}}']), 'pending')
        );
        $this->assertSame([2, 4], $this->execute($condition, 'customers', 'c'));
    }

    public function testProgrammaticFiltersAreValidatedByTheCompiler(): void
    {
        $condition = new Condition(
            (new PathParser())->parse('products__price'),
            new Filter(new Operator('=', ['sql' => '{{column}} = {{value}}', 'pattern' => '/^[0-9]+$/']), 'invalid')
        );
        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid value format for operator =: invalid.');
        (new SqlBuilderWhere('sqlite'))->build($condition);
    }

    public function testBetweenRequiresBothBounds(): void
    {
        $condition = new Condition(
            (new PathParser())->parse('products__price'),
            new Filter(new Operator('between:', [
                'sql' => '{{column}} BETWEEN {{value_1}} AND {{value_2}}',
                'cast' => ['list'],
            ]), '100')
        );
        $this->expectException(TranslatableUnderflowException::class);
        $this->expectExceptionMessage('Missing parameters for {{value_1}} or {{value_2}}.');
        (new SqlBuilderWhere('sqlite'))->build($condition);
    }

    /** @return iterable<string, array{string, string, string, list<int>}> */
    public static function correlatedAliasContexts(): iterable
    {
        yield 'same initial as the inner table' => ['invoices', 'p', '___payments[on:id=invoice_id]__COUNT(*)', [2, 3, 6]];
        yield 'outer alias in the automatic namespace' => ['invoices', '_____derafu_0', '___payments[on:id=invoice_id]__COUNT(*)', [2, 3, 6]];
        yield 'explicit inner alias remains valid' => ['invoices', 'p', '___payments[alias:paid,on:id=invoice_id]__COUNT(*)', [2, 3, 6]];
        yield 'the same table in both scopes' => ['payments', 'payments', '___payments[on:invoice_id=invoice_id]__COUNT(*)', [2, 3, 4, 5, 7, 8]];
    }

    /** @param list<int> $expected */
    #[DataProvider('correlatedAliasContexts')]
    public function testCorrelatedAliasesDoNotChangeTheSelectedRows(string $table, string $outerAlias, string $path, array $expected): void
    {
        $condition = new Condition((new PathParser())->parse($path), new Filter(new Operator('>', ['sql' => '{{column}} > CAST({{value}} AS NUMERIC)']), '1'));
        $this->assertSame($expected, $this->execute($condition, $table, $outerAlias));
    }

    public function testNestedExplicitAliasIsReservedBeforeThePrimaryAutomaticAlias(): void
    {
        $condition = new Condition(
            new Path([
                new Segment('invoices', ['subquery' => 'exists', 'on' => ['id' => 'customer_id']]),
                new Segment('payments', ['subquery' => 'exists', 'alias' => '_____derafu_0', 'on' => ['id' => 'invoice_id']]),
                new Segment('status'),
            ]),
            new Filter(new Operator('=', ['sql' => '{{column}} = {{value}}']), 'pending')
        );
        $this->assertSame([2, 4], $this->execute($condition, 'customers', 'c'));
    }

    /** @return iterable<string, array{string}> */
    public static function conflictingExplicitAliases(): iterable
    {
        yield 'outer alias' => ['___payments[alias:p,on:id=invoice_id]__COUNT(*)'];
        yield 'duplicate local alias' => ['___invoices[alias:entry,on:id=customer_id]___payments[alias:entry,on:id=invoice_id]__status'];
    }

    #[DataProvider('conflictingExplicitAliases')]
    public function testConflictingExplicitAliasesAreRejected(string $path): void
    {
        $condition = new Condition((new PathParser())->parse($path), new Filter(new Operator('=', ['sql' => '{{column}} = {{value}}']), '1'));
        $this->expectException(TranslatableInvalidArgumentException::class);
        (new SqlBuilderWhere('sqlite'))->build($condition, 'p');
    }
}
