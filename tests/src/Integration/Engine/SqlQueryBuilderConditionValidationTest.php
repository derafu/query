<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsQuery\Integration\Engine;

use Derafu\Query\Builder\AliasScope;
use Derafu\Query\Builder\Sql\SqlBuilderWhere;
use Derafu\Query\Builder\Sql\SqlQuery;
use Derafu\Query\Builder\SqlQueryBuilder;
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
use RuntimeException;
use stdClass;

#[UsesClass(AliasScope::class)]
#[CoversClass(SqlQueryBuilder::class)]
#[UsesClass(SqlBuilderWhere::class)]
#[UsesClass(SqlQuery::class)]
#[UsesClass(PdoEngine::class)]
#[UsesClass(CompositeCondition::class)]
#[UsesClass(Condition::class)]
#[UsesClass(ExpressionParser::class)]
#[UsesClass(Filter::class)]
#[UsesClass(FilterParser::class)]
#[UsesClass(Path::class)]
#[UsesClass(PathParser::class)]
#[UsesClass(Segment::class)]
#[UsesClass(Operator::class)]
#[UsesClass(OperatorLoader::class)]
#[UsesClass(OperatorManager::class)]
final class SqlQueryBuilderConditionValidationTest extends TestCase
{
    private SqlQueryBuilder $builder;

    private ExpressionParser $parser;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $sql = file_get_contents(__DIR__ . '/../../../fixtures/functional/query_config.sql');
        $this->assertIsString($sql);
        $pdo->exec($sql);

        $operators = (new OperatorLoader())->loadFromFile(__DIR__ . '/../../../../resources/operators.yaml');
        $this->parser = new ExpressionParser(new PathParser(), new FilterParser(new OperatorManager($operators)));
        $this->builder = new SqlQueryBuilder(new PdoEngine($pdo), $this->parser);
        $this->builder->from('products')->select('id')->where('id?>0');
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function invalidConditions(): iterable
    {
        $values = [
            'null' => null,
            'integer' => 123,
            'float' => 1.5,
            'true' => true,
            'false' => false,
            'object' => new stdClass(),
            'stringable object' => new RuntimeException('id?=1'),
        ];
        foreach (['where', 'nestedWhere', 'andWhere', 'orWhere', 'orWhereMixed', 'andWhereOr', 'andWhereOrNested', 'having'] as $route) {
            foreach ($values as $type => $value) {
                yield $route . ': ' . $type => [$route, $value];
            }
        }
    }

    #[DataProvider('invalidConditions')]
    public function testRejectsInvalidArrayElements(string $route, mixed $value): void
    {
        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('The value at "condition" must be of type');

        match ($route) {
            'where' => $this->builder->where([$value]),
            'nestedWhere' => $this->builder->where(['group' => ['nested' => $value]]),
            'andWhere' => $this->builder->andWhere([$value]),
            'orWhere' => $this->builder->orWhere([$value]),
            'orWhereMixed' => $this->builder->orWhere([['id?=1'], $value]),
            'andWhereOr' => $this->builder->andWhereOr([$value]),
            'andWhereOrNested' => $this->builder->andWhereOr([['nested' => $value]]),
            'having' => $this->builder->having([$value]),
            default => self::fail('Unknown validation route.'),
        };
    }

    public function testPreservesNestedConditionsAndOrGroups(): void
    {
        $computing = $this->parser->parse('category?=computing');
        $cheap = CompositeCondition::and()->add($this->parser->parse('price?<50'));

        $rows = $this->builder
            ->where(['nested' => [$computing, $cheap]])
            ->orWhere([['name?=Pen'], $this->parser->parse('name?=Notebook')])
            ->andWhereOr(['cheap' => $cheap, 'laptop' => 'name?=Laptop'])
            ->orderBy('id')
            ->execute();

        $this->assertSame([['id' => 2], ['id' => 3], ['id' => 4]], $rows);
    }
}
