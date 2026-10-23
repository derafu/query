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
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

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
final class SqlQueryBuilderGroupingTest extends TestCase
{
    public function testAndWhereFiltersTheWholePrecedingOrGroup(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $sql = file_get_contents(__DIR__ . '/../../../fixtures/functional/query_config.sql');
        $this->assertIsString($sql);
        $pdo->exec($sql);

        $operators = (new OperatorLoader())->loadFromFile(__DIR__ . '/../../../../resources/operators.yaml');
        $parser = new ExpressionParser(new PathParser(), new FilterParser(new OperatorManager($operators)));
        $builder = new SqlQueryBuilder(new PdoEngine($pdo), $parser);

        $rows = $builder->from('products')->select('id')
            ->where('category?=computing')
            ->orWhere('name?=Pen')
            ->andWhere('price?<50')
            ->orderBy('id')
            ->execute();

        // (computing OR Pen) AND price < 50 keeps Mouse and Pen. Laptop fails
        // the price condition; Notebook satisfies only the price condition.
        $this->assertSame([['id' => 2], ['id' => 3]], $rows);
    }
}
