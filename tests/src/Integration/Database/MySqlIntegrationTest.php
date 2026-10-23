<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsQuery\Integration\Database;

use Derafu\Query\Bridge\DoctrineDBALQueryBuilderConditionApplier;
use Derafu\Query\Bridge\DoctrineORM\Dql\MySqlBinaryStringFunction;
use Derafu\Query\Bridge\DoctrineORM\NumericComparisonType;
use Derafu\Query\Bridge\DoctrineORMQueryBuilderConditionApplier;
use Derafu\Query\Bridge\Exception\UnsupportedOperatorException;
use Derafu\Query\Bridge\Illuminate\CompiledCondition;
use Derafu\Query\Bridge\IlluminateQueryBuilderConditionApplier;
use Derafu\Query\Builder\AliasScope;
use Derafu\Query\Builder\Sql\SqlBuilderWhere;
use Derafu\Query\Builder\Sql\SqlQuery;
use Derafu\Query\Builder\SqlQueryBuilder;
use Derafu\Query\Config\JsonConfigLoader;
use Derafu\Query\Config\QueryConfig;
use Derafu\Query\Config\YamlConfigLoader;
use Derafu\Query\Engine\DoctrineEngine;
use Derafu\Query\Engine\PdoEngine;
use Derafu\Query\Filter\CompositeCondition;
use Derafu\Query\Filter\CompositeExpressionParser;
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
use Derafu\TestsQuery\Fixture\RealDatabaseContainer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[UsesClass(AliasScope::class)]
#[CoversClass(CompiledCondition::class)]
#[CoversClass(IlluminateQueryBuilderConditionApplier::class)]
#[CoversClass(DoctrineDBALQueryBuilderConditionApplier::class)]
#[CoversClass(NumericComparisonType::class)]
#[CoversClass(MySqlBinaryStringFunction::class)]
#[CoversClass(DoctrineORMQueryBuilderConditionApplier::class)]
#[CoversClass(UnsupportedOperatorException::class)]
#[CoversClass(SqlBuilderWhere::class)]
#[CoversClass(SqlQuery::class)]
#[CoversClass(SqlQueryBuilder::class)]
#[CoversClass(JsonConfigLoader::class)]
#[CoversClass(QueryConfig::class)]
#[CoversClass(YamlConfigLoader::class)]
#[CoversClass(DoctrineEngine::class)]
#[CoversClass(PdoEngine::class)]
#[CoversClass(CompositeCondition::class)]
#[CoversClass(CompositeExpressionParser::class)]
#[CoversClass(Condition::class)]
#[CoversClass(ExpressionParser::class)]
#[CoversClass(Filter::class)]
#[CoversClass(FilterParser::class)]
#[CoversClass(Path::class)]
#[CoversClass(PathParser::class)]
#[CoversClass(Segment::class)]
#[CoversClass(Operator::class)]
#[CoversClass(OperatorLoader::class)]
#[CoversClass(OperatorManager::class)]
final class MySqlIntegrationTest extends AbstractRealDatabaseQueryTestCase
{
    public static function setUpBeforeClass(): void
    {
        static::$server = RealDatabaseContainer::mysql();
    }
}
