<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsQuery\Integration\Bridge\DoctrineORM;

use Derafu\Query\Bridge\DoctrineORM\NumericComparisonType;
use Derafu\Query\Bridge\DoctrineORMQueryBuilderConditionApplier;
use Derafu\Query\Bridge\Exception\UnsupportedOperatorException;
use Derafu\Query\Builder\AliasScope;
use Derafu\Query\Builder\Sql\SqlBuilderWhere;
use Derafu\Query\Builder\Sql\SqlQuery;
use Derafu\Query\Filter\CompositeCondition;
use Derafu\Query\Filter\Condition;
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
use Derafu\TestsQuery\Integration\Bridge\DoctrineORM\Entity\Association\Account;
use Derafu\TestsQuery\Integration\Bridge\DoctrineORM\Entity\Association\AccountProfile;
use Derafu\TestsQuery\Integration\Bridge\DoctrineORM\Entity\Association\Enrollment;
use Derafu\TestsQuery\Integration\Bridge\DoctrineORM\Entity\Association\RegionalAccount;
use Derafu\TestsQuery\Integration\Bridge\DoctrineORM\Entity\Association\Team;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AliasScope::class)]
#[CoversClass(DoctrineORMQueryBuilderConditionApplier::class)]
#[CoversClass(NumericComparisonType::class)]
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
class DoctrineORMAssociationSubqueryTest extends TestCase
{
    private EntityManager $em;

    private ExpressionParserInterface $parser;

    protected function setUp(): void
    {
        $config = ORMSetup::createConfig(false, null, new ArrayCachePool());
        $config->setMetadataDriverImpl(new AttributeDriver([__DIR__ . '/Entity/Association']));
        $config->enableNativeLazyObjects(true);
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);
        $this->em = new EntityManager($connection, $config);
        $classes = [Account::class, Team::class, AccountProfile::class, RegionalAccount::class, Enrollment::class];
        (new SchemaTool($this->em))->createSchema(array_map($this->em->getClassMetadata(...), $classes));

        $connection->executeStatement("INSERT INTO association_teams(teamKey,status,score) VALUES ('t','active',10),('u','inactive',20),('v','active',30)");
        $connection->executeStatement("INSERT INTO association_accounts(accountKey,status,primary_team) VALUES ('a','active','t'),('b','inactive','u'),('c','active',NULL)");
        $connection->executeStatement("INSERT INTO association_profiles(profileKey,status,account_key) VALUES ('p','active','a'),('q','inactive','b'),('r','active',NULL)");
        $connection->executeStatement("INSERT INTO association_memberships(account_key,team_key) VALUES ('a','t'),('a','u'),('b','u')");
        $connection->executeStatement("INSERT INTO association_follows(account_key,team_key) VALUES ('a','t'),('b','u')");
        $connection->executeStatement("INSERT INTO association_regional_accounts(region,code,primary_team) VALUES ('eu','same','t'),('us','same','u')");
        $connection->executeStatement("INSERT INTO association_enrollments(account_key,sequence,primary_team) VALUES ('a',1,'t'),('a',2,'u'),('b',1,'u')");
        $operators = (new OperatorLoader())->loadFromFile(__DIR__ . '/../../../../../resources/operators.yaml');
        $this->parser = new ExpressionParser(new PathParser(), new FilterParser(new OperatorManager($operators)));
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->close();
        $this->em->close();
    }

    /**
     * @return iterable<string, array{class-string, string, string, list<string|int>}>
     */
    public static function associations(): iterable
    {
        yield 'unidirectional many to one' => [Account::class, 'accountKey', '___primaryTeam__status?=active', ['a']];
        yield 'owning one to one' => [AccountProfile::class, 'profileKey', '___account__status?=active', ['p']];
        yield 'inverse one to one' => [Account::class, 'accountKey', '___profile__status?=active', ['a']];
        yield 'owning many to many' => [Account::class, 'accountKey', '___teams__status?=active', ['a']];
        yield 'inverse many to many' => [Team::class, 'teamKey', '___members__status?=active', ['t', 'u']];
        yield 'unidirectional many to many' => [Account::class, 'accountKey', '___followedTeams__status?=active', ['a']];
        yield 'empty to one' => [Account::class, 'accountKey', '___primaryTeam?is:empty', ['c']];
        yield 'present to one' => [Account::class, 'accountKey', '___primaryTeam?isnot:empty', ['a', 'b']];
        yield 'empty inverse to one' => [Account::class, 'accountKey', '___profile?is:empty', ['c']];
        yield 'present inverse to one' => [Account::class, 'accountKey', '___profile?isnot:empty', ['a', 'b']];
        yield 'empty many to many' => [Account::class, 'accountKey', '___teams?is:empty', ['c']];
        yield 'to one count zero' => [Account::class, 'accountKey', '___primaryTeam__COUNT(*)?=0', ['c']];
        yield 'to one count one' => [Account::class, 'accountKey', '___primaryTeam__COUNT(*)?>0', ['a', 'b']];
        yield 'to one sum' => [Account::class, 'accountKey', '___primaryTeam__SUM(score)?=10', ['a']];
        yield 'to one aggregate null' => [Account::class, 'accountKey', '___primaryTeam__SUM(score)?is:null', ['c']];
        yield 'to one aggregate not null' => [Account::class, 'accountKey', '___primaryTeam__SUM(score)?isnot:null', ['a', 'b']];
        yield 'many to many sum' => [Account::class, 'accountKey', '___teams__SUM(score)?=30', ['a']];
        yield 'many to many count without size shortcut' => [Account::class, 'accountKey', '___teams__COUNT(*)?=2', ['a']];
        yield 'inverse many to many count' => [Team::class, 'teamKey', '___members__COUNT(*)?=2', ['u']];
        yield 'composite root identifier' => [RegionalAccount::class, 'region', '___primaryTeam__status?=active', ['eu']];
        yield 'association in root identifier' => [Enrollment::class, 'sequence', '___primaryTeam__status?=active', [1]];
        yield 'multiple associations' => [Account::class, 'accountKey', '___teams___members__status?=inactive', ['a', 'b']];
        yield 'multiple association count' => [Account::class, 'accountKey', '___teams___members__COUNT(*)?=3', ['a']];
    }

    /**
     * @param class-string $entity
     * @param list<string|int> $expected
     */
    #[DataProvider('associations')]
    public function testSubqueriesStayCorrelatedAcrossMappedAssociations(string $entity, string $key, string $expression, array $expected): void
    {
        $qb = $this->em->createQueryBuilder()->select('root.' . $key)->from($entity, 'root')->orderBy('root.' . $key);
        (new DoctrineORMQueryBuilderConditionApplier())->apply($qb, $this->parser->parse($expression));

        $this->assertSame($expected, $qb->getQuery()->getSingleColumnResult());
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function typedAggregates(): iterable
    {
        yield 'count decimal threshold' => ['___teams__COUNT(*)?>1.5', ['a']];
        yield 'count fractional equality is not truncated' => ['___teams__COUNT(*)?=1.5', []];
        yield 'count fractional threshold below one' => ['___teams__COUNT(*)?<0.5', ['c']];
        yield 'count list' => ['___teams__COUNT(*)?in:0,2', ['a', 'c']];
        yield 'count range' => ['___teams__COUNT(*)?between:1.5,2.5', ['a']];
        yield 'scientific notation' => ['___teams__COUNT(*)?=2e0', ['a']];
        yield 'negative threshold' => ['___teams__COUNT(*)?>-0.5', ['a', 'b', 'c']];
        yield 'decimal sum' => ['___teams__SUM(amount)?=3.75', ['a']];
        yield 'decimal average' => ['___teams__AVG(amount)?=1.875', ['a']];
        yield 'integer average' => ['___teams__AVG(score)?=15', ['a']];
        yield 'decimal minimum' => ['___teams__MIN(amount)?=1.25', ['a']];
        yield 'decimal maximum' => ['___teams__MAX(amount)?=2.50', ['a', 'b']];
        yield 'numeric looking text minimum' => ['___teams__MIN(code)?=00123', ['a']];
        yield 'text maximum' => ['___teams__MAX(code)?=123', ['a', 'b']];
        yield 'date minimum' => ['___teams__MIN(recordedOn)?=2026-10-01', ['a']];
        yield 'date maximum' => ['___teams__MAX(recordedOn)?=2026-10-02', ['a', 'b']];
        yield 'integer range' => ['___teams__SUM(score)?between:20,30', ['a', 'b']];
    }

    /** @param list<string> $expected */
    #[DataProvider('typedAggregates')]
    public function testAggregateComparisonsUseTheMappedType(string $expression, array $expected): void
    {
        $connection = $this->em->getConnection();
        $connection->executeStatement("UPDATE association_teams SET amount = '1.25', code = '00123', recordedOn = '2026-10-01' WHERE teamKey = 't'");
        $connection->executeStatement("UPDATE association_teams SET amount = '2.50', code = '123', recordedOn = '2026-10-02' WHERE teamKey = 'u'");
        $qb = $this->em->createQueryBuilder()->select('root.accountKey')->from(Account::class, 'root')->orderBy('root.accountKey');
        (new DoctrineORMQueryBuilderConditionApplier())->apply($qb, $this->parser->parse($expression));

        $this->assertSame($expected, $qb->getQuery()->getSingleColumnResult());
    }

    /** @return iterable<string, array{string}> */
    public static function invalidNumericOperands(): iterable
    {
        yield 'scalar' => ['___teams__COUNT(*)?=invalid'];
        yield 'list' => ['___teams__COUNT(*)?in:1,invalid'];
        yield 'range' => ['___teams__SUM(score)?between:0,invalid'];
    }

    #[DataProvider('invalidNumericOperands')]
    public function testInvalidNumericOperandsAreRejectedBeforeExecution(string $expression): void
    {
        $qb = $this->em->createQueryBuilder()->select('root.accountKey')->from(Account::class, 'root');
        $this->expectException(TranslatableInvalidArgumentException::class);
        (new DoctrineORMQueryBuilderConditionApplier())->apply($qb, $this->parser->parse($expression));
    }

    public function testNumericParametersKeepTheirOriginalPrecision(): void
    {
        $threshold = '123456789012345678901234567890.123456789';
        $qb = $this->em->createQueryBuilder()->select('root.accountKey')->from(Account::class, 'root');
        (new DoctrineORMQueryBuilderConditionApplier())->apply($qb, $this->parser->parse('___teams__SUM(score)?>' . $threshold));

        $this->assertCount(1, $qb->getParameters());
        foreach ($qb->getParameters() as $parameter) {
            $this->assertSame($threshold, $parameter->getValue());
            $this->assertSame(NumericComparisonType::class, $parameter->getType());
        }
        $this->assertSame([], $qb->getQuery()->getSingleColumnResult());
    }

    public function testCompositeConditionsKeepEachAggregateParameterType(): void
    {
        $condition = new CompositeCondition('AND');
        $condition->add($this->parser->parse('___teams__COUNT(*)?>1.5'));
        $condition->add($this->parser->parse('___followedTeams__SUM(score)?=10'));
        $condition->add($this->parser->parse('status?=active'));
        $qb = $this->em->createQueryBuilder()->select('root.accountKey')->from(Account::class, 'root');
        (new DoctrineORMQueryBuilderConditionApplier())->apply($qb, $condition);

        $this->assertSame(['a'], $qb->getQuery()->getSingleColumnResult());
    }

    /** @return iterable<string, array{AbstractPlatform, string}> */
    public static function numericPlatforms(): iterable
    {
        yield 'sqlite' => [new SQLitePlatform(), 'CAST(? AS NUMERIC)'];
        yield 'postgresql' => [new PostgreSQLPlatform(), 'CAST(? AS NUMERIC)'];
        yield 'mysql' => [new MySQLPlatform(), '?'];
        yield 'sql server' => [new SQLServerPlatform(), '?'];
        yield 'oracle' => [new OraclePlatform(), '?'];
    }

    #[DataProvider('numericPlatforms')]
    public function testNumericBindingUsesThePlatformRepresentation(AbstractPlatform $platform, string $expected): void
    {
        $this->assertSame($expected, (new NumericComparisonType())->convertToDatabaseValueSQL('?', $platform));
    }

    /** @return iterable<string, array{bool, bool}> */
    public static function aliasAllocationContexts(): iterable
    {
        yield 'same applier' => [false, false];
        yield 'another applier' => [true, false];
        yield 'cloned query builder' => [false, true];
        yield 'cloned builder and another applier' => [true, true];
    }

    #[DataProvider('aliasAllocationContexts')]
    public function testRepeatedApplicationsKeepAutomaticAliasesUnique(bool $anotherApplier, bool $clone): void
    {
        $qb = $this->em->createQueryBuilder()->select('_____derafu_0.accountKey')->from(Account::class, '_____derafu_0');
        $applier = new DoctrineORMQueryBuilderConditionApplier();
        $applier->apply($qb, $this->parser->parse('___teams__COUNT(*)?>1.5'));
        if ($clone) {
            $qb = clone $qb;
        }
        if ($anotherApplier) {
            $applier = new DoctrineORMQueryBuilderConditionApplier();
        }
        $applier->apply($qb, $this->parser->parse('___followedTeams__SUM(score)?=10'));

        $this->assertSame(['a'], $qb->getQuery()->getSingleColumnResult());
    }

    public function testExplicitAliasesAreReservedBeforeOtherSubqueriesAllocateAutomaticNames(): void
    {
        $condition = CompositeCondition::and()
            ->add($this->parser->parse('___teams__COUNT(*)?>1.5'))
            ->add(new Condition(
                new Path([new Segment('followedTeams', ['subquery' => 'exists', 'alias' => '_____derafu_0']), new Segment('SUM(score)')]),
                $this->parser->parse('___followedTeams__SUM(score)?=10')->getFilter()
            ));
        $qb = $this->em->createQueryBuilder()->select('root.accountKey')->from(Account::class, 'root');
        (new DoctrineORMQueryBuilderConditionApplier())->apply($qb, $condition);

        $this->assertMatchesRegularExpression('/\.followedTeams _____derafu_0\b/', $qb->getDQL());
        $this->assertSame(['a'], $qb->getQuery()->getSingleColumnResult());
    }

    public function testAnExplicitAliasMatchingAFieldNameRemainsValid(): void
    {
        $qb = $this->em->createQueryBuilder()->select('root.accountKey')->from(Account::class, 'root');
        $qb->andWhere('root.status = :status')->setParameter('status', 'active');
        (new DoctrineORMQueryBuilderConditionApplier())->apply($qb, $this->parser->parse('___teams[alias:status]__COUNT(*)?>1.5'));

        $this->assertStringContainsString('.teams status', $qb->getDQL());
        $this->assertSame(['a'], $qb->getQuery()->getSingleColumnResult());
    }

    public function testAnExplicitAliasCannotHideTheCorrelatedRoot(): void
    {
        $qb = $this->em->createQueryBuilder()->select('root.accountKey')->from(Account::class, 'root');
        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('Alias "root" is already used in this query scope.');
        (new DoctrineORMQueryBuilderConditionApplier())->apply($qb, $this->parser->parse('___primaryTeam[alias:root]__SUM(score)?=10'));
    }
}
