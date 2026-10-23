<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsQuery\Integration\Bridge;

use Derafu\Query\Bridge\DoctrineDBALQueryBuilderConditionApplier;
use Derafu\Query\Bridge\Illuminate\CompiledCondition;
use Derafu\Query\Bridge\IlluminateQueryBuilderConditionApplier;
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
use Doctrine\DBAL\DriverManager;
use Illuminate\Database\Capsule\Manager as Capsule;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[UsesClass(AliasScope::class)]
#[CoversClass(PathParser::class)]
#[UsesClass(DoctrineDBALQueryBuilderConditionApplier::class)]
#[UsesClass(IlluminateQueryBuilderConditionApplier::class)]
#[UsesClass(SqlQueryBuilder::class)]
#[UsesClass(CompiledCondition::class)]
#[UsesClass(SqlBuilderWhere::class)]
#[UsesClass(SqlQuery::class)]
#[UsesClass(PdoEngine::class)]
#[UsesClass(CompositeCondition::class)]
#[UsesClass(Condition::class)]
#[UsesClass(ExpressionParser::class)]
#[UsesClass(Filter::class)]
#[UsesClass(FilterParser::class)]
#[UsesClass(Path::class)]
#[UsesClass(Segment::class)]
#[UsesClass(Operator::class)]
#[UsesClass(OperatorLoader::class)]
#[UsesClass(OperatorManager::class)]
final class CompositeJoinSqliteTest extends TestCase
{
    /**
     * @return array<string,array{string}>
     */
    public static function consumers(): array
    {
        return ['SQL builder' => ['sql'], 'Doctrine DBAL' => ['dbal'], 'Illuminate' => ['illuminate']];
    }

    #[DataProvider('consumers')]
    public function testCompositeForeignKeyUsesEveryOnCorrespondence(string $consumer): void
    {
        $operators = (new OperatorLoader())->loadFromFile(__DIR__ . '/../../../../resources/operators.yaml');
        $parser = new ExpressionParser(new PathParser(), new FilterParser(new OperatorManager($operators)));
        $condition = $parser->parse(
            'orders[alias:o]__order_items[alias:i,on:id=order_id,on:branch_id=branch_id]__name?=First branch item'
        );
        $sql = file_get_contents(__DIR__ . '/../../../fixtures/integration/composite_join.sql');
        $this->assertIsString($sql);
        $columns = ['o.id', 'o.branch_id', 'i.name'];

        if ($consumer === 'sql') {
            $pdo = new PDO('sqlite::memory:');
            $pdo->exec($sql);
            $builder = new SqlQueryBuilder(new PdoEngine($pdo), $parser);
            $rows = $builder->from('orders', 'o')->select($columns)->where($condition)->execute();
        } elseif ($consumer === 'dbal') {
            $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
            $pdo = $connection->getNativeConnection();
            $this->assertInstanceOf(PDO::class, $pdo);
            $pdo->exec($sql);
            $builder = $connection->createQueryBuilder()->select(...$columns)->from('orders', 'o');
            (new DoctrineDBALQueryBuilderConditionApplier())->apply($builder, $condition);
            $rows = $builder->executeQuery()->fetchAllAssociative();
        } else {
            $capsule = new Capsule();
            $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
            $connection = $capsule->getConnection();
            $connection->getPdo()->exec($sql);
            $builder = $connection->query()->from('orders', 'o')->select($columns);
            (new IlluminateQueryBuilderConditionApplier())->apply($builder, $condition);
            $rows = $builder->get()->map(static fn (object $row): array => get_object_vars($row))->all();
        }

        // The same order id exists in two branches: omitting either predicate
        // returns an extra order instead of this single matching row.
        $this->assertSame([['id' => 1, 'branch_id' => 10, 'name' => 'First branch item']], $rows);
    }
}
