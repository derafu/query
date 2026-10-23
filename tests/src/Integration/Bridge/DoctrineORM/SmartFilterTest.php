<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsQuery\Integration\Bridge\DoctrineORM;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use ApiPlatform\Metadata\HeaderParameter;
use ApiPlatform\Metadata\Parameter;
use ApiPlatform\Metadata\QueryParameter;
use Derafu\Query\Bridge\ApiPlatform\SmartFilter;
use Derafu\Query\Bridge\DoctrineORMQueryBuilderConditionApplier;
use Derafu\Query\Bridge\Exception\UnsupportedOperatorException;
use Derafu\Query\Builder\AliasScope;
use Derafu\Query\Builder\Sql\SqlBuilderWhere;
use Derafu\Query\Builder\Sql\SqlQuery;
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
use Derafu\TestsQuery\Integration\Bridge\DoctrineORM\Entity\Customer;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\QueryBuilder as DoctrineORMQueryBuilder;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[UsesClass(AliasScope::class)]
#[CoversClass(SmartFilter::class)]
#[UsesClass(DoctrineORMQueryBuilderConditionApplier::class)]
#[UsesClass(CompositeExpressionParser::class)]
#[UsesClass(UnsupportedOperatorException::class)]
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
final class SmartFilterTest extends TestCase
{
    private EntityManager $em;

    private SmartFilter $filter;

    protected function setUp(): void
    {
        $config = ORMSetup::createConfig(false, null, new ArrayCachePool());
        $config->setMetadataDriverImpl(new AttributeDriver([__DIR__ . '/Entity']));
        $config->enableNativeLazyObjects(true);
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);
        $this->em = new EntityManager($connection, $config);
        $pdo = $connection->getNativeConnection();
        $this->assertInstanceOf(PDO::class, $pdo);
        foreach (['billing_schema.sql', 'billing_data.sql'] as $fixture) {
            $sql = file_get_contents(__DIR__ . '/../../../../fixtures/integration/' . $fixture);
            $this->assertIsString($sql);
            $pdo->exec($sql);
        }
        $operators = (new OperatorLoader())->loadFromFile(__DIR__ . '/../../../../../resources/operators.yaml');
        $parser = new ExpressionParser(new PathParser(), new FilterParser(new OperatorManager($operators)));
        $this->filter = new SmartFilter(new CompositeExpressionParser($parser), new DoctrineORMQueryBuilderConditionApplier());
    }

    private function builder(): DoctrineORMQueryBuilder
    {
        return $this->em->createQueryBuilder()->select('c.id')->from(Customer::class, 'c')->orderBy('c.id');
    }

    public function testQueryAndHeaderParametersAreBothAccepted(): void
    {
        foreach ([new QueryParameter(property: 'status'), new HeaderParameter(property: 'status')] as $parameter) {
            $parameter->setValue('=active');
            $qb = $this->builder();
            $this->filter->apply($qb, new QueryNameGenerator(), Customer::class, context: ['parameter' => $parameter]);
            $this->assertSame([['id' => 1], ['id' => 2], ['id' => 4]], $qb->getQuery()->getScalarResult());
        }
    }

    public function testFullCompositeExpressionIsAcceptedForTheConfiguredProperty(): void
    {
        $parameter = (new QueryParameter(property: '__derafu_smart_filter'))->setValue('status?=active&&type?=person');
        $qb = $this->builder();
        $this->filter->apply($qb, new QueryNameGenerator(), Customer::class, context: ['parameter' => $parameter]);
        $this->assertSame([['id' => 1]], $qb->getQuery()->getScalarResult());
    }

    /**
     * @return iterable<string,array{mixed}>
     */
    public static function invalidParameters(): iterable
    {
        yield 'array' => [[]];
        yield 'string' => ['status'];
        yield 'integer' => [123];
        yield 'boolean' => [false];
        yield 'unrelated object' => [new stdClass()];
    }

    #[DataProvider('invalidParameters')]
    public function testInvalidParameterObjectsAreRejected(mixed $parameter): void
    {
        $qb = $this->builder();
        $dql = $qb->getDQL();
        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('parameter');
        try {
            $this->filter->apply($qb, new QueryNameGenerator(), Customer::class, context: ['parameter' => $parameter]);
        } finally {
            $this->assertSame($dql, $qb->getDQL());
        }
    }

    public function testAbsentAndNullParametersLeaveTheQueryUnchanged(): void
    {
        foreach ([[], ['parameter' => null]] as $context) {
            $qb = $this->builder();
            $dql = $qb->getDQL();
            $this->filter->apply($qb, new QueryNameGenerator(), Customer::class, context: $context);
            $this->assertSame($dql, $qb->getDQL());
            $this->assertCount(0, $qb->getParameters());
        }
    }

    /**
     * @return iterable<string,array{Parameter}>
     */
    public static function ignoredParameters(): iterable
    {
        yield 'no property' => [(new QueryParameter())->setValue('=active')];
        yield 'empty property' => [(new QueryParameter(property: ''))->setValue('=active')];
        yield 'missing value' => [new QueryParameter(property: 'status')];
        yield 'empty value' => [(new QueryParameter(property: 'status'))->setValue('')];
        yield 'array value' => [(new HeaderParameter(property: 'status'))->setValue(['=active'])];
        yield 'integer value' => [(new QueryParameter(property: 'status'))->setValue(123)];
        yield 'malformed expression' => [(new QueryParameter(property: 'status'))->setValue('invalid')];
        yield 'unsupported DQL operator' => [(new QueryParameter(property: 'created_at'))->setValue('date:20260101')];
    }

    #[DataProvider('ignoredParameters')]
    public function testIgnoredParametersLeaveTheQueryUnchanged(Parameter $parameter): void
    {
        $qb = $this->builder();
        $dql = $qb->getDQL();
        $this->filter->apply($qb, new QueryNameGenerator(), Customer::class, context: ['parameter' => $parameter]);
        $this->assertSame($dql, $qb->getDQL());
        $this->assertCount(0, $qb->getParameters());
    }

    public function testDescriptionIsDelegatedToParameterMetadata(): void
    {
        $this->assertSame([], $this->filter->getDescription(Customer::class));
    }
}
