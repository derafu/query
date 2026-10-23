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

use Derafu\Query\Bridge\Illuminate\CompiledCondition;
use Derafu\Query\Bridge\IlluminateQueryBuilderConditionApplier;
use Derafu\Query\Builder\AliasScope;
use Derafu\Query\Builder\Sql\SqlBuilderWhere;
use Derafu\Query\Builder\Sql\SqlQuery;
use Derafu\Query\Filter\CompositeCondition;
use Derafu\Query\Filter\Condition;
use Derafu\Query\Filter\Contract\CompositeConditionInterface;
use Derafu\Query\Filter\Contract\ExpressionParserInterface;
use Derafu\Query\Filter\ExpressionParser;
use Derafu\Query\Filter\Filter;
use Derafu\Query\Filter\FilterParser;
use Derafu\Query\Filter\Path;
use Derafu\Query\Filter\PathParser;
use Derafu\Query\Filter\Segment;
use Derafu\Query\Operator\Operator;
use Derafu\Query\Operator\OperatorLoader;
use Derafu\Query\Operator\OperatorManager;
use Derafu\TestsQuery\Fixture\Models\IlluminateCustomer;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as IlluminateQueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../fixtures/models/IlluminateCustomer.php';

#[UsesClass(AliasScope::class)]
#[CoversClass(IlluminateQueryBuilderConditionApplier::class)]
#[CoversClass(CompiledCondition::class)]
#[UsesClass(SqlBuilderWhere::class)]
#[UsesClass(SqlQuery::class)]
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
class IlluminateQueryBuilderSqliteTest extends TestCase
{
    private Connection $connection;

    private IlluminateQueryBuilderConditionApplier $applier;

    private ExpressionParserInterface $expressionParser;

    protected function setUp(): void
    {
        $capsule = new Capsule();
        $capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $this->connection = $capsule->getConnection();

        $pdo = $this->connection->getPdo();
        $sql = file_get_contents(
            __DIR__ . '/../../../fixtures/integration/billing_schema.sql'
        );
        $this->assertIsString($sql);
        $pdo->exec($sql);
        $sql = file_get_contents(
            __DIR__ . '/../../../fixtures/integration/billing_data.sql'
        );
        $this->assertIsString($sql);
        $pdo->exec($sql);

        $pathParser = new PathParser();
        $loader = new OperatorLoader();
        $operators = $loader->loadFromFile(
            __DIR__ . '/../../../../resources/operators.yaml'
        );
        $manager = new OperatorManager($operators);
        $filterParser = new FilterParser($manager);
        $this->expressionParser = new ExpressionParser($pathParser, $filterParser);

        $this->applier = new IlluminateQueryBuilderConditionApplier();
    }

    /**
     * @param array{sql:string,parameters:array<string,mixed>} $sql
     * @param array{table?:string,alias?:string,select?:literal-string,distinct?:bool,where?:string|list<string|list<string>>,andWhere?:string|list<string|list<string>>,orWhere?:string|list<string|list<string>>,andWhereOr?:list<string|list<string>>,having?:string|list<string|list<string>>,composite?:string,innerJoin?:array{table:string,condition:literal-string,alias?:string},leftJoin?:array{table:string,condition:literal-string,alias?:string},rightJoin?:array{table:string,condition:literal-string,alias?:string},groupBy?:literal-string|list<literal-string>,orderBy?:array<literal-string,literal-string>,limit?:int,offset?:int} $query
     */
    #[DataProvider('queryProvider')]
    public function testSqlQueries(
        string $description,
        array $sql,
        array $query
    ): void {
        $expected = array_map(
            fn ($row) => (array) $row,
            $this->connection->select($sql['sql'], $sql['parameters'])
        );

        $qb = $this->buildQueryFromConfig($query);
        $actual = $qb->get()->map(fn ($row) => (array) $row)->all();

        $this->assertSame($expected, $actual, $description);
    }

    public function testWhereCanBeAppliedBeforeTheConsumerSetsFrom(): void
    {
        $qb = $this->connection->query()->select('id');
        $this->applier->apply($qb, $this->expressionParser->parse('status?=active'));
        $qb->from('customers')->orderBy('id');

        $this->assertSame([['id' => 1], ['id' => 2], ['id' => 4]], $qb->get()->map(fn ($row) => (array) $row)->all());
    }

    public function testEloquentBuilderReceivesTheCompiledCondition(): void
    {
        $previous = Model::getConnectionResolver();
        $resolver = new ConnectionResolver(['default' => $this->connection]);
        $resolver->setDefaultConnection('default');
        Model::setConnectionResolver($resolver);
        try {
            $builder = IlluminateCustomer::query()->orderBy('id');
            $this->applier->apply($builder, $this->expressionParser->parse('status?=active'));
            $this->assertSame([1, 2, 4], $builder->pluck('id')->all());
        } finally {
            if ($previous === null) {
                Model::unsetConnectionResolver();
            } else {
                Model::setConnectionResolver($previous);
            }
        }
    }

    public function testFromRawPreservesTheTableAlias(): void
    {
        $qb = $this->connection->query()->fromRaw('products as p')->select('p.id')->orderBy('p.id');
        $condition = $this->expressionParser->parse('p__price?>500');
        $this->applier->apply($qb, $condition);

        $this->assertSame(['500'], $qb->getBindings());
        $this->assertSame([['id' => 1], ['id' => 2]], $qb->get()->map(fn ($row) => (array) $row)->all());
    }

    public function testFromSubqueryBindingsRemainSeparateFromWhereBindings(): void
    {
        $qb = $this->connection->query()
            ->fromRaw('(SELECT * FROM products WHERE category = ?) as p', ['electronics'])
            ->select('p.id')
            ->orderBy('p.id');
        $condition = $this->expressionParser->parse('p__price?>1000');
        $this->applier->apply($qb, $condition);

        $bindings = $qb->getRawBindings();
        $this->assertSame(['electronics'], $bindings['from']);
        $this->assertSame(['1000'], $bindings['where']);
        $this->assertSame(['electronics', '1000'], $qb->getBindings());
        $this->assertSame([['id' => 1]], $qb->get()->map(fn ($row) => (array) $row)->all());
    }

    public function testFromRawAliasIsUsedForExistsCorrelation(): void
    {
        $qb = $this->connection->query()->fromRaw('customers as c')->select('c.id')->orderBy('c.id');
        $condition = $this->expressionParser->parse('___invoices[on:id=customer_id]__status?=paid');
        $this->applier->apply($qb, $condition);

        $this->assertSame(['paid'], $qb->getBindings());
        $this->assertSame([['id' => 1], ['id' => 2]], $qb->get()->map(fn ($row) => (array) $row)->all());
    }

    public function testCompiledWhereAndHavingPreserveExistingBindings(): void
    {
        $qb = $this->connection->table('customers as c')
            ->select('c.type')
            ->selectRaw('COUNT(*) AS total')
            ->where('c.status', 'active')
            ->groupBy('c.type')
            ->havingRaw('COUNT(*) >= ?', [0]);
        $this->applier->apply($qb, $this->expressionParser->parse('c__id?>1'));
        $this->applier->applyHaving($qb, $this->expressionParser->parse('c__type?=company'));

        $bindings = $qb->getRawBindings();
        $this->assertSame(['active', '1'], $bindings['where']);
        $this->assertSame([0, 'company'], $bindings['having']);
        $this->assertSame(['active', '1', 0, 'company'], $qb->getBindings());
        $this->assertSame([['type' => 'company', 'total' => 2]], $qb->get()->map(fn ($row) => (array) $row)->all());
    }

    public function testCompiledConditionsKeepSqlLikeValuesInBindings(): void
    {
        $value = "John Smith' OR 1=1 --";
        $qb = $this->connection->table('customers')->select('id');
        $this->applier->apply($qb, $this->expressionParser->parse('name?=' . $value));

        $this->assertSame([$value], $qb->getBindings());
        $this->assertStringNotContainsString($value, $qb->toSql());
        $this->assertSame([], $qb->get()->all());
    }

    public function testCompiledConditionPreservesQuotedIdentifiersAndLiterals(): void
    {
        $sql = <<<'SQL'
SELECT ":value" AS quoted_identifier, `:value` AS backtick_identifier,
       ':value' AS literal_value, :value AS bound_value
FROM (SELECT 'identifier' AS ":value")
/* :value */
-- :value
SQL;
        $compiled = new CompiledCondition($sql, ['value' => 'bound']);
        $this->assertSame(['bound'], $compiled->getBindings());
        $rows = $this->connection->select(
            $compiled->getValue($this->connection->getQueryGrammar()),
            $compiled->getBindings()
        );
        $this->assertCount(1, $rows);
        $this->assertSame([
            'quoted_identifier' => 'identifier',
            'backtick_identifier' => 'identifier',
            'literal_value' => ':value',
            'bound_value' => 'bound',
        ], (array) $rows[0]);
    }

    /**
     * @return array<string,array{string,array{sql:string,parameters:array<string,mixed>},array{table?:string,alias?:string,select?:literal-string,distinct?:bool,where?:string|list<string|list<string>>,andWhere?:string|list<string|list<string>>,orWhere?:string|list<string|list<string>>,andWhereOr?:list<string|list<string>>,having?:string|list<string|list<string>>,composite?:string,innerJoin?:array{table:string,condition:literal-string,alias?:string},leftJoin?:array{table:string,condition:literal-string,alias?:string},rightJoin?:array{table:string,condition:literal-string,alias?:string},groupBy?:literal-string|list<literal-string>,orderBy?:array<literal-string,literal-string>,limit?:int,offset?:int}}>
     */
    public static function queryProvider(): array
    {
        $cases = require __DIR__ . '/../../../fixtures/integration/queries_integration.php';
        $data = [];

        foreach ($cases['cases'] as $name => $case) {
            $data[$name] = [
                $case['description'],
                $case['sql'],
                $case['query'],
            ];
        }

        return $data;
    }

    /**
     * Builds an Illuminate QueryBuilder from a query config array.
     *
     * @param array{table?:string,alias?:string,select?:literal-string,distinct?:bool,where?:string|list<string|list<string>>,andWhere?:string|list<string|list<string>>,orWhere?:string|list<string|list<string>>,andWhereOr?:list<string|list<string>>,having?:string|list<string|list<string>>,composite?:string,innerJoin?:array{table:string,condition:literal-string,alias?:string},leftJoin?:array{table:string,condition:literal-string,alias?:string},rightJoin?:array{table:string,condition:literal-string,alias?:string},groupBy?:literal-string|list<literal-string>,orderBy?:array<literal-string,literal-string>,limit?:int,offset?:int} $config
     */
    private function buildQueryFromConfig(array $config): IlluminateQueryBuilder
    {
        $qb = $this->connection->query();

        // FROM / table.
        if (isset($config['table'])) {
            $alias = $config['alias'] ?? null;
            $qb->from($config['table'], $alias);
        }

        // SELECT.
        if (isset($config['select'])) {
            $qb->selectRaw($config['select']);
        }

        // DISTINCT.
        if (isset($config['distinct']) && $config['distinct']) {
            $qb->distinct();
        }

        // Explicit JOINs (non-path based).
        foreach (['innerJoin', 'leftJoin', 'rightJoin'] as $joinType) {
            if (!isset($config[$joinType])) {
                continue;
            }
            $join = $config[$joinType];
            $tableRef = $join['table'] . (isset($join['alias']) ? ' as ' . $join['alias'] : '');
            $condition = $join['condition'];
            match ($joinType) {
                'innerJoin' => $qb->join(
                    $tableRef,
                    fn ($j) => $j->whereRaw($condition)
                ),
                'leftJoin' => $qb->leftJoin(
                    $tableRef,
                    fn ($j) => $j->whereRaw($condition)
                ),
                'rightJoin' => $qb->rightJoin(
                    $tableRef,
                    fn ($j) => $j->whereRaw($condition)
                ),
            };
        }

        // WHERE conditions via the applier.
        $composite = $this->buildWhereComposite($config);
        if ($composite !== null) {
            $this->applier->apply($qb, $composite);
        }

        // HAVING conditions via the applier.
        if (isset($config['having'])) {
            $havingComposite = CompositeCondition::and();
            $this->addToComposite($havingComposite, $config['having']);
            $this->applier->applyHaving($qb, $havingComposite);
        }

        // GROUP BY.
        if (isset($config['groupBy'])) {
            $groups = is_array($config['groupBy'])
                ? $config['groupBy']
                : [$config['groupBy']];
            $qb->groupByRaw(implode(', ', $groups));
        }

        // ORDER BY.
        if (isset($config['orderBy'])) {
            foreach ($config['orderBy'] as $column => $direction) {
                $qb->orderByRaw($column . ' ' . $direction);
            }
        }

        // LIMIT / OFFSET.
        if (isset($config['limit'])) {
            $qb->limit($config['limit']);
        }
        if (isset($config['offset'])) {
            $qb->offset($config['offset']);
        }

        return $qb;
    }

    /**
     * Builds a CompositeCondition from the where/andWhere/orWhere/andWhereOr
     * config keys, following the same composition logic as SqlQueryBuilder.
     *
     * @param array{table?:string,alias?:string,select?:literal-string,distinct?:bool,where?:string|list<string|list<string>>,andWhere?:string|list<string|list<string>>,orWhere?:string|list<string|list<string>>,andWhereOr?:list<string|list<string>>,having?:string|list<string|list<string>>,composite?:string,innerJoin?:array{table:string,condition:literal-string,alias?:string},leftJoin?:array{table:string,condition:literal-string,alias?:string},rightJoin?:array{table:string,condition:literal-string,alias?:string},groupBy?:literal-string|list<literal-string>,orderBy?:array<literal-string,literal-string>,limit?:int,offset?:int} $config
     */
    private function buildWhereComposite(array $config): ?CompositeConditionInterface
    {
        $composite = null;

        // where.
        if (isset($config['where'])) {
            $composite = CompositeCondition::and();
            $this->addToComposite($composite, $config['where']);
        }

        // andWhere is applied before orWhere (matches QueryConfig::applyTo order).
        if (isset($config['andWhere'])) {
            if ($composite === null) {
                $composite = CompositeCondition::and();
            }
            $this->addToComposite($composite, $config['andWhere']);
        }

        // orWhere wraps the existing composite in an OR.
        if (isset($config['orWhere'])) {
            if ($composite === null) {
                $composite = CompositeCondition::and();
                $this->addToComposite($composite, $config['orWhere']);
            } else {
                $existing = $composite;
                $composite = CompositeCondition::or();
                $composite->add($existing);
                $this->addOrGroupToComposite($composite, $config['orWhere']);
            }
        }

        // andWhereOr adds an OR group ANDed to the current composite.
        if (isset($config['andWhereOr'])) {
            if ($composite === null) {
                $composite = CompositeCondition::and();
            }
            $or = CompositeCondition::or();
            foreach ($config['andWhereOr'] as $group) {
                $and = CompositeCondition::and();
                $this->addToComposite($and, $group);
                $or->add($and);
            }
            $composite->add($or);
        }

        return $composite;
    }

    /**
     * Adds one or more expression strings (or arrays of them) to a composite.
     *
     * @param string|list<string|list<string>> $conditions
     */
    private function addToComposite(
        CompositeConditionInterface $composite,
        string|array $conditions
    ): void {
        if (is_string($conditions)) {
            $composite->add($this->expressionParser->parse($conditions));
            return;
        }

        foreach ($conditions as $condition) {
            if (is_string($condition)) {
                $composite->add($this->expressionParser->parse($condition));
            } elseif (is_array($condition)) {
                $sub = CompositeCondition::and();
                $this->addToComposite($sub, $condition);
                $composite->add($sub);
            }
        }
    }

    /**
     * Adds orWhere value to an OR composite, handling flat arrays and
     * arrays-of-arrays (multiple OR groups).
     *
     * @param string|list<string|list<string>> $conditions
     */
    private function addOrGroupToComposite(
        CompositeConditionInterface $orComposite,
        string|array $conditions
    ): void {
        if (is_string($conditions)) {
            $and = CompositeCondition::and();
            $and->add($this->expressionParser->parse($conditions));
            $orComposite->add($and);
            return;
        }

        // Check if any element is itself an array (multiple OR groups).
        $hasSubArrays = false;
        foreach ($conditions as $item) {
            if (is_array($item)) {
                $hasSubArrays = true;
                break;
            }
        }

        if ($hasSubArrays) {
            foreach ($conditions as $item) {
                $and = CompositeCondition::and();
                $this->addToComposite($and, is_array($item) ? $item : [$item]);
                $orComposite->add($and);
            }
        } else {
            $and = CompositeCondition::and();
            $this->addToComposite($and, $conditions);
            $orComposite->add($and);
        }
    }
}
