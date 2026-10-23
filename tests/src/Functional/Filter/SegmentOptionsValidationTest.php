<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsQuery\Functional\Filter;

use Derafu\Query\Bridge\DoctrineDBALQueryBuilderConditionApplier;
use Derafu\Query\Bridge\DoctrineORMQueryBuilderConditionApplier;
use Derafu\Query\Bridge\Illuminate\CompiledCondition;
use Derafu\Query\Bridge\IlluminateQueryBuilderConditionApplier;
use Derafu\Query\Builder\AliasScope;
use Derafu\Query\Builder\Sql\SqlBuilderWhere;
use Derafu\Query\Builder\Sql\SqlQuery;
use Derafu\Query\Builder\SqlQueryBuilder;
use Derafu\Query\Engine\PdoEngine;
use Derafu\Query\Filter\CompositeCondition;
use Derafu\Query\Filter\Condition;
use Derafu\Query\Filter\Contract\ConditionInterface;
use Derafu\Query\Filter\ExpressionParser;
use Derafu\Query\Filter\Filter;
use Derafu\Query\Filter\FilterParser;
use Derafu\Query\Filter\Path;
use Derafu\Query\Filter\PathParser;
use Derafu\Query\Filter\Segment;
use Derafu\Query\Filter\SegmentOptionsTrait;
use Derafu\Query\Operator\Operator;
use Derafu\Query\Operator\OperatorLoader;
use Derafu\Query\Operator\OperatorManager;
use Derafu\TestsQuery\Integration\Bridge\DoctrineORM\ArrayCachePool;
use Derafu\TestsQuery\Integration\Bridge\DoctrineORM\Entity\Customer;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\ORMSetup;
use Illuminate\Database\Capsule\Manager as Capsule;
use PDO;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[UsesClass(AliasScope::class)]
#[CoversTrait(SegmentOptionsTrait::class)]
#[UsesClass(CompiledCondition::class)]
#[UsesClass(SqlBuilderWhere::class)]
#[UsesClass(SqlQuery::class)]
#[UsesClass(SqlQueryBuilder::class)]
#[UsesClass(PdoEngine::class)]
#[UsesClass(DoctrineDBALQueryBuilderConditionApplier::class)]
#[UsesClass(DoctrineORMQueryBuilderConditionApplier::class)]
#[UsesClass(IlluminateQueryBuilderConditionApplier::class)]
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
final class SegmentOptionsValidationTest extends TestCase
{
    private ExpressionParser $parser;

    protected function setUp(): void
    {
        $operators = (new OperatorLoader())->loadFromFile(
            __DIR__ . '/../../../../resources/operators.yaml'
        );
        $this->parser = new ExpressionParser(
            new PathParser(),
            new FilterParser(new OperatorManager($operators))
        );
    }

    /**
     * Compiles through the real consumer, without mocks or reflection.
     */
    private function compile(string $consumer, ConditionInterface $condition): string
    {
        if ($consumer === 'where') {
            return (new SqlBuilderWhere('sqlite'))->build($condition, 'customers')->getQuery()['sql'];
        }
        if ($consumer === 'sql') {
            $builder = new SqlQueryBuilder(new PdoEngine(new PDO('sqlite::memory:')), $this->parser);
            $builder->from('customers', 'customers')->where($condition);

            return $builder->getQuery()->getQuery()['sql'];
        }
        if ($consumer === 'dbal') {
            $builder = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true])
                ->createQueryBuilder()->select('*')->from('customers', 'customers');
            (new DoctrineDBALQueryBuilderConditionApplier())->apply($builder, $condition);

            return $builder->getSQL();
        }
        if ($consumer === 'illuminate') {
            $capsule = new Capsule();
            $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
            $builder = $capsule->getConnection()->query()->from('customers', 'customers');
            (new IlluminateQueryBuilderConditionApplier())->apply($builder, $condition);

            return $builder->toSql();
        }

        $config = ORMSetup::createConfig(false, null, new ArrayCachePool());
        $config->setMetadataDriverImpl(new AttributeDriver([
            __DIR__ . '/../../Integration/Bridge/DoctrineORM/Entity',
        ]));
        $config->enableNativeLazyObjects(true);
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);
        $builder = (new EntityManager($connection, $config))->createQueryBuilder()
            ->select('customers')->from(Customer::class, 'customers');
        (new DoctrineORMQueryBuilderConditionApplier())->apply($builder, $condition);

        return $builder->getDQL();
    }

    /**
     * @param array<string,string|array<string,string>|null> $baseOptions
     * @param array<string,string|array<string,string>|null>|null $joinOptions
     */
    private function condition(array $baseOptions = [], ?array $joinOptions = null): ConditionInterface
    {
        $segments = [new Segment('customers', $baseOptions)];
        if ($joinOptions !== null) {
            $segments[] = new Segment('invoices', $joinOptions);
        }
        $segments[] = new Segment('id');

        return new Condition(new Path($segments), $this->parser->parse('id?is:null')->getFilter());
    }

    /**
     * @return array<string,array{string,string,string}>
     */
    public static function invalidOptions(): array
    {
        $cases = [];
        foreach (['sql', 'where', 'dbal', 'illuminate', 'orm'] as $consumer) {
            $cases[$consumer . ' base alias'] = [$consumer, 'baseAlias', 'customers.alias'];
            $cases[$consumer . ' joined alias'] = [$consumer, 'joinedAlias', 'invoices.alias'];
            $cases[$consumer . ' subquery'] = [$consumer, 'subquery', 'customers.subquery'];
            if ($consumer !== 'where') {
                $cases[$consumer . ' join type'] = [$consumer, 'join', 'invoices.join'];
            }
            if ($consumer !== 'orm') {
                $cases[$consumer . ' on type'] = [$consumer, 'on', 'invoices.on'];
                $cases[$consumer . ' on key'] = [$consumer, 'onKey', 'invoices.on.123'];
            }
        }

        return $cases;
    }

    #[DataProvider('invalidOptions')]
    public function testConsumersRejectIncorrectOptionTypes(string $consumer, string $option, string $path): void
    {
        $condition = match ($option) {
            'baseAlias' => $this->condition(['alias' => ['unexpected' => 'value']]),
            'joinedAlias' => $this->condition([], ['alias' => ['unexpected' => 'value']]),
            'subquery' => $this->condition(['subquery' => ['unexpected' => 'value']]),
            'join' => $this->condition([], ['join' => ['unexpected' => 'value']]),
            'on' => $this->parser->parse('___invoices[on:invalid]?isnot:empty'),
            default => $this->parser->parse('___invoices[on:123=customer_id]?isnot:empty'),
        };

        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('"' . $path . '"');
        $this->compile($consumer, $condition);
    }

    /**
     * @return array<string,array{string}>
     */
    public static function consumers(): array
    {
        return [
            'SQL builder' => ['sql'],
            'SQL condition builder' => ['where'],
            'Doctrine DBAL' => ['dbal'],
            'Illuminate' => ['illuminate'],
            'Doctrine ORM' => ['orm'],
        ];
    }

    #[DataProvider('consumers')]
    public function testAbsentAndNullOptionsKeepDefaultsAndUnknownOptions(string $consumer): void
    {
        $expected = $this->compile($consumer, $this->condition([], [
            'on' => ['id' => 'customer_id'],
        ]));
        $condition = $this->condition(
            ['alias' => null, 'subquery' => null, 'extension' => ['custom' => 'value']],
            ['alias' => null, 'join' => null, 'on' => ['id' => 'customer_id']]
        );

        $this->assertSame($expected, $this->compile($consumer, $condition));
    }

    #[DataProvider('consumers')]
    public function testEmptyNullAndAbsentOnOptionsKeepTheirMeaning(string $consumer): void
    {
        $expected = $this->compile($consumer, $this->condition([], []));

        $this->assertSame($expected, $this->compile($consumer, $this->condition([], ['on' => null])));
        $this->assertSame($expected, $this->compile($consumer, $this->condition([], ['on' => []])));
    }

    public function testOrmContinuesToUseAssociationMappingsInsteadOfOnOptions(): void
    {
        $expected = $this->compile('orm', $this->condition([], ['on' => ['id' => 'customer_id']]));

        $this->assertSame($expected, $this->compile('orm', $this->condition([], ['on' => 'ignored'])));
        $this->assertStringContainsString('customers.invoices', $expected);
    }
}
