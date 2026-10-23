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
use Derafu\Query\Operator\OperatorManagerFactory;
use Derafu\Translation\Exception\Core\TranslatableRuntimeException;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[UsesClass(AliasScope::class)]
#[CoversClass(SqlQueryBuilder::class)]
#[CoversClass(OperatorManagerFactory::class)]
#[UsesClass(SqlBuilderWhere::class)]
#[UsesClass(SqlQuery::class)]
#[UsesClass(PdoEngine::class)]
#[UsesClass(CompositeCondition::class)]
#[UsesClass(Condition::class)]
#[UsesClass(ExpressionParser::class)]
#[UsesClass(Filter::class)]
#[CoversClass(FilterParser::class)]
#[UsesClass(Path::class)]
#[UsesClass(PathParser::class)]
#[CoversClass(Segment::class)]
#[UsesClass(Operator::class)]
#[UsesClass(OperatorLoader::class)]
#[UsesClass(OperatorManager::class)]
final class SqlQueryBuilderPublicApiTest extends TestCase
{
    private SqlQueryBuilder $builder;

    private ExpressionParser $parser;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $sql = file_get_contents(__DIR__ . '/../../../fixtures/functional/query_config.sql');
        $this->assertIsString($sql);
        $pdo->exec($sql);
        $manager = (new OperatorManagerFactory(new OperatorLoader()))->create(
            __DIR__ . '/../../../../resources/operators.yaml'
        );
        $this->parser = new ExpressionParser(new PathParser(), new FilterParser($manager));
        $this->builder = new SqlQueryBuilder(new PdoEngine($pdo), $this->parser);
    }

    /**
     * @return iterable<string,array{string}>
     */
    public static function initialConditions(): iterable
    {
        yield 'andWhere' => ['andWhere'];
        yield 'orWhere' => ['orWhere'];
    }

    #[DataProvider('initialConditions')]
    public function testConditionMethodsWorkBeforeWhere(string $method): void
    {
        $this->builder->from('products')->select(['id']);
        $this->builder->$method('price?<10');
        $this->assertSame([['id' => 3], ['id' => 4]], $this->builder->orderBy(['id'])->execute());
    }

    public function testAndWhereOrCanStartWithACompositeCondition(): void
    {
        $group = CompositeCondition::or()
            ->add($this->parser->parse('id?=1'))
            ->add($this->parser->parse('id?=3'));
        $rows = $this->builder->from('products')->select(['id'])->andWhereOr($group)->orderBy(['id'])->execute();
        $this->assertSame([['id' => 1], ['id' => 3]], $rows);
    }

    public function testOrWhereAcceptsACompositeCondition(): void
    {
        $group = CompositeCondition::or()
            ->add($this->parser->parse('id?=3'))
            ->add($this->parser->parse('id?=4'));
        $rows = $this->builder->from('products')->select(['id'])->where('id?=1')->orWhere($group)->orderBy(['id'])->execute();
        $this->assertSame([['id' => 1], ['id' => 3], ['id' => 4]], $rows);
    }

    public function testCrossJoinCombinesRowsFromBothTables(): void
    {
        $rows = $this->builder->from('products', 'p')->select(['p.id', 's.quantity'])
            ->crossJoin('stock', 's')->where('s__quantity?>1')->orderBy(['p.id'])->execute();
        $this->assertSame([
            ['id' => 1, 'quantity' => 3], ['id' => 2, 'quantity' => 3],
            ['id' => 3, 'quantity' => 3], ['id' => 4, 'quantity' => 3],
        ], $rows);
    }

    public function testUnknownJoinTypeIsRejected(): void
    {
        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid join type SIDEWAYS.');
        $this->builder->from('products')->join('stock', 'products.id = stock.product_id', 'SIDEWAYS');
    }

    public function testQueryWithoutAnExplicitOrInferredTableIsRejected(): void
    {
        $this->expectException(TranslatableRuntimeException::class);
        $this->expectExceptionMessage('No table specified for query.');
        $this->builder->getQuery();
    }

    public function testFactoryPropagatesAnUnreadableOperatorsFile(): void
    {
        $this->expectException(TranslatableInvalidArgumentException::class);
        (new OperatorManagerFactory(new OperatorLoader()))->create(__DIR__ . '/../../../fixtures/missing-operators.yaml');
    }

    public function testUnknownFilterOperatorIsRejected(): void
    {
        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('No valid operator found');
        $this->parser->parse('id?unknown');
    }

    public function testEmptySegmentNamesAreRejected(): void
    {
        $this->expectException(TranslatableInvalidArgumentException::class);
        new Segment('');
    }

    public function testSegmentOptionsPreserveValuesAndDefaults(): void
    {
        $segment = new Segment('customers', ['alias' => 'c']);
        $this->assertSame('c', $segment->getOption('alias'));
        $this->assertSame('inner', $segment->getOption('join', 'inner'));
        $this->assertNull($segment->getOption('missing'));
    }
}
