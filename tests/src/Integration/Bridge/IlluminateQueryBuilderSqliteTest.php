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

use Derafu\Array\ArrayReader;
use Derafu\Query\Bridge\Illuminate\CompiledExpression;
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
use Derafu\Value\ValueType;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as IlluminateQueryBuilder;
use Illuminate\Database\Query\JoinClause;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../fixtures/models/IlluminateCustomer.php';

#[UsesClass(AliasScope::class)]
#[CoversClass(IlluminateQueryBuilderConditionApplier::class)]
#[CoversClass(CompiledExpression::class)]
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
     * @param ArrayReader $query
     */
    #[DataProvider('queryProvider')]
    public function testSqlQueries(
        string $description,
        array $sql,
        ArrayReader $query
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

    public function testEloquentBuilderReceivesTheCompiledExpression(): void
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

    public function testExistingJoinExpressionIsNotDuplicated(): void
    {
        $qb = $this->connection->query()
            ->from('customers', 'c')
            ->leftJoin($this->connection->raw('invoices as i'), 'c.id', '=', 'i.customer_id')
            ->select('c.id')
            ->orderBy('c.id');
        $condition = $this->expressionParser->parse(
            'customers[alias:c]__invoices[on:id=customer_id,join:left,alias:i]__status?=paid'
        );

        $this->applier->apply($qb, $condition);

        $this->assertCount(1, $qb->joins ?? []);
        $this->assertSame(['paid'], $qb->getBindings());
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

    public function testCompiledExpressionsKeepSqlLikeValuesInBindings(): void
    {
        $value = "John Smith' OR 1=1 --";
        $qb = $this->connection->table('customers')->select('id');
        $this->applier->apply($qb, $this->expressionParser->parse('name?=' . $value));

        $this->assertSame([$value], $qb->getBindings());
        $this->assertStringNotContainsString($value, $qb->toSql());
        $this->assertSame([], $qb->get()->all());
    }

    public function testCompiledExpressionPreservesQuotedIdentifiersAndLiterals(): void
    {
        $sql = <<<'SQL'
SELECT ":value" AS quoted_identifier, `:value` AS backtick_identifier,
       ':value' AS literal_value, :value AS bound_value
FROM (SELECT 'identifier' AS ":value")
/* :value */
-- :value
SQL;
        $compiled = new CompiledExpression($sql, ['value' => 'bound']);
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
     * @return array<string,array{string,array{sql:string,parameters:array<string,mixed>},ArrayReader}>
     */
    public static function queryProvider(): array
    {
        $cases = ArrayReader::fromValue(require __DIR__ . '/../../../fixtures/integration/queries_integration.php');
        $data = [];

        foreach ($cases->requireMapOf('cases', ValueType::array()) as $name => $case) {
            $case = ArrayReader::fromArray($case);
            $sql = $case->requireReader('sql');
            $data[$name] = [
                $case->requireString('description'),
                [
                    'sql' => $sql->requireString('sql'),
                    'parameters' => $sql->requireMap('parameters'),
                ],
                $case->requireReader('query', ValueType::map()),
            ];
        }

        return $data;
    }

    /**
     * Builds an Illuminate QueryBuilder from a query configuration reader.
     *
     * @param ArrayReader $config
     */
    private function buildQueryFromConfig(ArrayReader $config): IlluminateQueryBuilder
    {
        $qb = $this->connection->query();

        // FROM / table.
        if ($config->hasValue('table')) {
            $alias = $config->getString('alias');
            $qb->from($config->requireString('table'), $alias);
        }

        // SELECT.
        if ($config->hasValue('select')) {
            $qb->select(new CompiledExpression($config->requireString('select')));
        }

        // DISTINCT.
        if ($config->hasValue('distinct') && $config->requireBool('distinct')) {
            $qb->distinct();
        }

        // Explicit JOINs (non-path based).
        foreach (['innerJoin', 'leftJoin', 'rightJoin'] as $joinType) {
            if (!$config->hasValue($joinType)) {
                continue;
            }
            $join = $config->requireReader($joinType, ValueType::map());
            $alias = $join->getString('alias');
            $tableRef = $join->requireString('table') . ($alias !== null ? ' as ' . $alias : '');
            $condition = new CompiledExpression($join->requireString('condition'));
            match ($joinType) {
                'innerJoin' => $qb->join(
                    $tableRef,
                    fn (JoinClause $j) => $j->whereRaw($condition)
                ),
                'leftJoin' => $qb->leftJoin(
                    $tableRef,
                    fn (JoinClause $j) => $j->whereRaw($condition)
                ),
                'rightJoin' => $qb->rightJoin(
                    $tableRef,
                    fn (JoinClause $j) => $j->whereRaw($condition)
                ),
            };
        }

        // WHERE conditions via the applier.
        $composite = $this->buildWhereComposite($config);
        if ($composite !== null) {
            $this->applier->apply($qb, $composite);
        }

        // HAVING conditions via the applier.
        if ($config->hasValue('having')) {
            $havingComposite = CompositeCondition::and();
            $this->addToComposite(
                $havingComposite,
                $config->require('having', ValueType::oneOf(
                    ValueType::string(),
                    ValueType::listOf(ValueType::oneOf(
                        ValueType::string(),
                        ValueType::listOf(ValueType::string())
                    ))
                ))
            );
            $this->applier->applyHaving($qb, $havingComposite);
        }

        // GROUP BY.
        if ($config->hasValue('groupBy')) {
            $groupBy = $config->require('groupBy', ValueType::oneOf(
                ValueType::string(),
                ValueType::listOf(ValueType::string())
            ));
            $groups = is_array($groupBy) ? $groupBy : [$groupBy];
            $qb->groupBy(new CompiledExpression(implode(', ', $groups)));
        }

        // ORDER BY.
        if ($config->hasValue('orderBy')) {
            foreach ($config->requireMapOf('orderBy', ValueType::string()) as $column => $direction) {
                $direction = ValueType::literal('asc', 'desc')->parse(
                    strtolower($direction),
                    path: 'orderBy.' . $column
                );
                $qb->orderBy(new CompiledExpression($column), $direction);
            }
        }

        // LIMIT / OFFSET.
        if ($config->hasValue('limit')) {
            $qb->limit($config->requireInt('limit'));
        }
        if ($config->hasValue('offset')) {
            $qb->offset($config->requireInt('offset'));
        }

        return $qb;
    }

    /**
     * Builds a CompositeCondition from the where/andWhere/orWhere/andWhereOr
     * config keys, following the same composition logic as SqlQueryBuilder.
     *
     * @param ArrayReader $config
     */
    private function buildWhereComposite(ArrayReader $config): ?CompositeConditionInterface
    {
        $conditionType = ValueType::oneOf(
            ValueType::string(),
            ValueType::listOf(ValueType::oneOf(
                ValueType::string(),
                ValueType::listOf(ValueType::string())
            ))
        );
        $composite = null;

        // where.
        if ($config->hasValue('where')) {
            $composite = CompositeCondition::and();
            $this->addToComposite($composite, $config->require('where', $conditionType));
        }

        // andWhere is applied before orWhere (matches QueryConfig::applyTo order).
        if ($config->hasValue('andWhere')) {
            if ($composite === null) {
                $composite = CompositeCondition::and();
            }
            $this->addToComposite($composite, $config->require('andWhere', $conditionType));
        }

        // orWhere wraps the existing composite in an OR.
        if ($config->hasValue('orWhere')) {
            if ($composite === null) {
                $composite = CompositeCondition::and();
                $this->addToComposite($composite, $config->require('orWhere', $conditionType));
            } else {
                $existing = $composite;
                $composite = CompositeCondition::or();
                $composite->add($existing);
                $this->addOrGroupToComposite($composite, $config->require('orWhere', $conditionType));
            }
        }

        // andWhereOr adds an OR group ANDed to the current composite.
        if ($config->hasValue('andWhereOr')) {
            if ($composite === null) {
                $composite = CompositeCondition::and();
            }
            $or = CompositeCondition::or();
            $groups = $config->requireListOf('andWhereOr', ValueType::oneOf(
                ValueType::string(),
                ValueType::listOf(ValueType::string())
            ));
            foreach ($groups as $group) {
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
