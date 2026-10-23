<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsQuery\Config;

use Derafu\Query\Builder\AliasScope;
use Derafu\Query\Builder\Sql\SqlBuilderWhere;
use Derafu\Query\Builder\Sql\SqlQuery;
use Derafu\Query\Builder\SqlQueryBuilder;
use Derafu\Query\Config\QueryConfig;
use Derafu\Query\Engine\PdoEngine;
use Derafu\Query\Filter\CompositeCondition;
use Derafu\Query\Filter\Condition;
use Derafu\Query\Filter\ExpressionParser;
use Derafu\Query\Filter\Filter;
use Derafu\Query\Filter\FilterParser;
use Derafu\Query\Filter\Path;
use Derafu\Query\Filter\PathParser;
use Derafu\Query\Filter\Segment;
use Derafu\Query\Operator\Operator;
use Derafu\Query\Operator\OperatorLoader;
use Derafu\Query\Operator\OperatorManager;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[UsesClass(AliasScope::class)]
#[CoversClass(QueryConfig::class)]
#[UsesClass(SqlQueryBuilder::class)]
#[UsesClass(SqlBuilderWhere::class)]
#[UsesClass(SqlQuery::class)]
#[UsesClass(PdoEngine::class)]
#[UsesClass(ExpressionParser::class)]
#[UsesClass(PathParser::class)]
#[UsesClass(FilterParser::class)]
#[UsesClass(OperatorLoader::class)]
#[UsesClass(OperatorManager::class)]
#[UsesClass(Operator::class)]
#[UsesClass(Path::class)]
#[UsesClass(Segment::class)]
#[UsesClass(Filter::class)]
#[UsesClass(Condition::class)]
#[UsesClass(CompositeCondition::class)]
final class QueryConfigValidationTest extends TestCase
{
    private PDO $pdo;

    private ExpressionParser $parser;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $sql = file_get_contents(__DIR__ . '/../../../fixtures/functional/query_config.sql');
        $this->assertIsString($sql);
        $this->pdo->exec($sql);

        $operators = (new OperatorLoader())->loadFromFile(
            __DIR__ . '/../../../../resources/operators.yaml'
        );
        $this->parser = new ExpressionParser(
            new PathParser(),
            new FilterParser(new OperatorManager($operators))
        );
    }

    private function builder(): SqlQueryBuilder
    {
        return new SqlQueryBuilder(new PdoEngine($this->pdo), $this->parser);
    }

    /**
     * @return array<string,array{array<string,mixed>,string}>
     */
    public static function invalidConfigurations(): array
    {
        $cases = [];
        foreach (['table', 'alias'] as $key) {
            $cases[$key . ' is not a string'] = [[$key => 123], $key];
        }
        foreach (['limit', 'offset'] as $key) {
            $cases[$key . ' is a numeric string'] = [[$key => '10'], $key];
            $cases[$key . ' is a float'] = [[$key => 10.0], $key];
        }
        foreach ([0, 1, 0.0, 1.0, '', '0', 'true', 'false', 'enabled', [], new stdClass()] as $index => $distinct) {
            $cases['distinct has an invalid type ' . $index] = [
                ['distinct' => $distinct],
                'distinct',
            ];
        }
        foreach (['select', 'groupBy', 'orderBy'] as $key) {
            $cases[$key . ' is not columns'] = [[$key => false], $key];
            $cases[$key . ' contains a non-string'] = [[$key => ['valid', 123]], $key . '.1'];
            $cases[$key . ' contains a nested array'] = [[$key => ['name' => ['id']]], $key . '.name'];
        }
        foreach (['where', 'andWhere', 'orWhere', 'andWhereOr', 'having'] as $key) {
            $cases[$key . ' is not a condition'] = [[$key => 123], $key];
            $cases[$key . ' is an unrelated object'] = [[$key => new stdClass()], $key];
            $cases[$key . ' contains a nested null'] = [
                [$key => ['group' => ['id?=1', null]]],
                $key . '.group.1',
            ];
        }
        $cases['andWhereOr needs a group'] = [['andWhereOr' => 'id?=1'], 'andWhereOr'];

        foreach (['innerJoin', 'leftJoin', 'rightJoin', 'crossJoin'] as $key) {
            $cases[$key . ' is not an array'] = [[$key => 'stock'], $key];
            $cases[$key . ' is a list'] = [[$key => ['stock']], $key];
            $cases[$key . ' needs a table'] = [[$key => []], $key . '.table'];
            $cases[$key . ' table is not a string'] = [[$key => ['table' => 123]], $key . '.table'];
            $cases[$key . ' alias is not a string'] = [
                [$key => ['table' => 'stock', 'condition' => 'products.id = stock.product_id', 'alias' => []]],
                $key . '.alias',
            ];
            if ($key !== 'crossJoin') {
                $cases[$key . ' needs a condition'] = [[$key => ['table' => 'stock']], $key . '.condition'];
                $cases[$key . ' condition is not a string'] = [
                    [$key => ['table' => 'stock', 'condition' => false]],
                    $key . '.condition',
                ];
            }
        }

        return $cases;
    }

    /**
     * @param array<string,mixed> $data
     */
    #[DataProvider('invalidConfigurations')]
    public function testInvalidTypesAreRejectedBeforeApplyingConfiguration(array $data, string $path): void
    {
        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('"' . $path . '"');

        new QueryConfig($data);
    }

    /**
     * @return array<string,array{string|array<string>,string|array<string>,string|array<string>}>
     */
    public static function columnRepresentations(): array
    {
        return [
            'strings' => ['category, COUNT(*) AS count', 'category', 'category'],
            'lists' => [['category', 'COUNT(*) AS count'], ['category'], ['category']],
            'keyed arrays' => [
                ['label' => 'category', 'amount' => 'COUNT(*) AS count'],
                ['label' => 'category'],
                ['category' => 'ASC'],
            ],
            'sparse arrays' => [
                [2 => 'category', 7 => 'COUNT(*) AS count'],
                [9 => 'category'],
                [5 => 'category'],
            ],
        ];
    }

    /**
     * @param string|array<string> $select
     * @param string|array<string> $groupBy
     * @param string|array<string> $orderBy
     */
    #[DataProvider('columnRepresentations')]
    public function testColumnRepresentationsKeepTheirMeaning(
        string|array $select,
        string|array $groupBy,
        string|array $orderBy
    ): void {
        $data = compact('select', 'groupBy', 'orderBy') + ['table' => 'products'];
        $config = new QueryConfig($data);

        $rows = $config->applyTo($this->builder())->execute();

        $this->assertSame([
            ['category' => 'computing', 'count' => 2],
            ['category' => 'office', 'count' => 2],
        ], $rows);
        $this->assertSame($data, $config->getConfig());
    }

    /**
     * @return array<string,array{string,bool}>
     */
    public static function conditionObjects(): array
    {
        $cases = [];
        foreach (['where', 'andWhere', 'orWhere', 'andWhereOr', 'having'] as $key) {
            $cases[$key . ' condition'] = [$key, false];
            $cases[$key . ' composite'] = [$key, true];
        }

        return $cases;
    }

    #[DataProvider('conditionObjects')]
    public function testConditionObjectsAreAccepted(string $key, bool $composite): void
    {
        $condition = $this->parser->parse('id?=2');
        if ($composite) {
            $condition = CompositeCondition::and()->add($condition);
        }
        $config = new QueryConfig([
            'table' => 'products',
            'select' => 'id',
            'groupBy' => 'id',
            $key => $condition,
        ]);

        $this->assertSame([['id' => 2]], $config->applyTo($this->builder())->execute());
    }

    public function testNestedConditionsPreserveTheirGroupingAndObjects(): void
    {
        $condition = $this->parser->parse('price?<50');
        $data = [
            'table' => 'products',
            'select' => 'id',
            'where' => ['first' => ['price?>10']],
            'andWhere' => ['id?>0'],
            'orWhere' => [['id?=3', 'price?<10'], ['id?=99']],
            'andWhereOr' => ['group' => [$condition, 'id?>1'], ['id?=99']],
            'orderBy' => 'id',
        ];
        $config = new QueryConfig($data);

        $this->assertSame([['id' => 2], ['id' => 3]], $config->applyTo($this->builder())->execute());
        $this->assertSame($data, $config->getConfig());
    }

    /**
     * @return array<string,array{string,bool,array<array{id:int|null,quantity:int|null}>}>
     */
    public static function joins(): array
    {
        $expected = [
            'innerJoin' => [['id' => 1, 'quantity' => 3], ['id' => 2, 'quantity' => 0]],
            'leftJoin' => [
                ['id' => 1, 'quantity' => 3], ['id' => 2, 'quantity' => 0],
                ['id' => 3, 'quantity' => null], ['id' => 4, 'quantity' => null],
            ],
            'rightJoin' => [
                ['id' => null, 'quantity' => 1],
                ['id' => 1, 'quantity' => 3], ['id' => 2, 'quantity' => 0],
            ],
        ];
        $cases = [];
        foreach ($expected as $key => $rows) {
            $cases[$key . ' with alias'] = [$key, true, $rows];
            $cases[$key . ' without alias'] = [$key, false, $rows];
        }

        return $cases;
    }

    /**
     * @param array<array{id:int|null,quantity:int|null}> $expected
     */
    #[DataProvider('joins')]
    public function testJoinConfigurationsExecuteRealJoins(string $key, bool $withAlias, array $expected): void
    {
        $stock = $withAlias ? 's' : 'stock';
        $config = new QueryConfig([
            'table' => 'products',
            'alias' => 'p',
            'select' => ['p.id', $stock . '.quantity'],
            $key => [
                'table' => 'stock',
                'condition' => 'p.id = ' . $stock . '.product_id',
                'alias' => $withAlias ? 's' : null,
            ],
            'orderBy' => 'p.id',
        ]);

        $this->assertSame($expected, $config->applyTo($this->builder())->execute());
    }

    public function testCrossJoinDoesNotRequireACondition(): void
    {
        foreach ([null, 's'] as $alias) {
            $config = new QueryConfig([
                'table' => 'products',
                'select' => 'COUNT(*) AS matches',
                'crossJoin' => ['table' => 'stock', 'alias' => $alias],
            ]);

            $this->assertSame([['matches' => 12]], $config->applyTo($this->builder())->execute());
        }
    }

    public function testNullOptionsAreSkippedAndUnknownOptionsArePreserved(): void
    {
        $data = array_fill_keys([
            'alias', 'select', 'distinct', 'where', 'andWhere', 'orWhere', 'andWhereOr',
            'innerJoin', 'leftJoin', 'rightJoin', 'crossJoin', 'groupBy', 'having',
            'orderBy', 'limit', 'offset',
        ], null) + ['table' => 'products', 'extension' => ['custom' => true]];
        $config = new QueryConfig($data);
        $result = $config->applyTo($this->builder())->execute();
        $this->assertIsArray($result);

        $this->assertSame([1, 2, 3, 4], array_column($result, 'id'));
        $this->assertSame($data, $config->getConfig());
    }

    public function testTableCanBeInferredWhenItIsNull(): void
    {
        $config = new QueryConfig(['table' => null, 'where' => 'products__id?=2']);

        $this->assertSame([
            ['id' => 2, 'name' => 'Mouse', 'category' => 'computing', 'price' => 20],
        ], $config->applyTo($this->builder())->execute());
    }

    public function testLimitAndOffsetIncludeZero(): void
    {
        $config = new QueryConfig([
            'table' => 'products', 'select' => 'id', 'orderBy' => 'id', 'limit' => 1, 'offset' => 1,
        ]);
        $this->assertSame([['id' => 2]], $config->applyTo($this->builder())->execute());

        $empty = new QueryConfig(['table' => 'products', 'limit' => 0, 'offset' => 0]);
        $this->assertSame([], $empty->applyTo($this->builder())->execute());
    }

    public function testDistinctAcceptsBooleansAndOptionalNull(): void
    {
        foreach ([false, null] as $distinct) {
            $config = new QueryConfig(['table' => 'products', 'select' => 'category', 'distinct' => $distinct]);
            $rows = $config->applyTo($this->builder())->execute();
            $this->assertIsArray($rows);
            $this->assertCount(4, $rows);
        }

        $config = new QueryConfig(['table' => 'products', 'select' => 'category', 'distinct' => true]);
        $this->assertSame([
            ['category' => 'computing'],
            ['category' => 'office'],
        ], $config->applyTo($this->builder())->orderBy('category')->execute());

        $config = new QueryConfig(['table' => 'products', 'select' => 'category']);
        $rows = $config->applyTo($this->builder())->execute();
        $this->assertIsArray($rows);
        $this->assertCount(4, $rows);
    }
}
