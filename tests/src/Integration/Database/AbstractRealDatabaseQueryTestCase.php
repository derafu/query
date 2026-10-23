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
use Derafu\Query\Bridge\DoctrineORMQueryBuilderConditionApplier;
use Derafu\Query\Bridge\Exception\UnsupportedOperatorException;
use Derafu\Query\Builder\SqlQueryBuilder;
use Derafu\Query\Config\JsonConfigLoader;
use Derafu\Query\Config\QueryConfig;
use Derafu\Query\Config\YamlConfigLoader;
use Derafu\Query\Engine\DoctrineEngine;
use Derafu\Query\Engine\PdoEngine;
use Derafu\Query\Filter\CompositeExpressionParser;
use Derafu\Query\Filter\ExpressionParser;
use Derafu\Query\Filter\FilterParser;
use Derafu\Query\Filter\PathParser;
use Derafu\Query\Operator\OperatorLoader;
use Derafu\Query\Operator\OperatorManager;
use Derafu\TestsQuery\Fixture\RealDatabaseContainer;
use Derafu\TestsQuery\Integration\Bridge\DoctrineORM\ArrayCachePool;
use Derafu\TestsQuery\Integration\Bridge\DoctrineORM\DoctrineORMAssociationSubqueryTest;
use Derafu\TestsQuery\Integration\Bridge\DoctrineORM\Entity\Association\Account;
use Derafu\TestsQuery\Integration\Bridge\DoctrineORM\Entity\Association\AccountProfile;
use Derafu\TestsQuery\Integration\Bridge\DoctrineORM\Entity\Association\Enrollment;
use Derafu\TestsQuery\Integration\Bridge\DoctrineORM\Entity\Association\RegionalAccount;
use Derafu\TestsQuery\Integration\Bridge\DoctrineORM\Entity\Association\Team;
use Derafu\TestsQuery\Integration\Bridge\DoctrineORM\Entity\Customer;
use Derafu\TestsQuery\Integration\Bridge\DoctrineORM\Entity\Invoice;
use Derafu\TestsQuery\Integration\Bridge\DoctrineORM\Entity\InvoiceDetail;
use Derafu\TestsQuery\Integration\Bridge\DoctrineORM\Entity\Payment;
use Derafu\TestsQuery\Integration\Bridge\DoctrineORM\Entity\Product;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Real-server scenarios shared by PostgreSQL and MySQL, without mocks. */
abstract class AbstractRealDatabaseQueryTestCase extends TestCase
{
    protected static RealDatabaseContainer $server;

    private Connection $connection;

    private EntityManager $em;

    private ExpressionParser $parser;

    protected function setUp(): void
    {
        $this->connection = static::$server->database();
        $config = ORMSetup::createConfig(false, null, new ArrayCachePool());
        $config->setMetadataDriverImpl(new AttributeDriver([__DIR__ . '/../Bridge/DoctrineORM/Entity']));
        $config->enableNativeLazyObjects(true);
        $this->em = new EntityManager($this->connection, $config);
        $classes = [Customer::class, Invoice::class, InvoiceDetail::class, Payment::class, Product::class,
            Account::class, AccountProfile::class, Enrollment::class, RegionalAccount::class, Team::class];
        (new SchemaTool($this->em))->createSchema(array_map($this->em->getClassMetadata(...), $classes));
        /** @var array<string, list<array<string, int|string|null>>> $rows */
        $rows = require __DIR__ . '/../../../fixtures/integration/real_database/data.php';
        foreach ($rows as $table => $records) {
            foreach ($records as $record) {
                $this->connection->insert($table, $record);
            }
        }
        $operators = (new OperatorLoader())->loadFromFile(__DIR__ . '/../../../../resources/operators.yaml');
        $this->parser = new ExpressionParser(new PathParser(), new FilterParser(new OperatorManager($operators)));
    }

    protected function tearDown(): void
    {
        if (isset($this->em)) {
            $this->em->close();
        }
        if (isset($this->connection)) {
            static::$server->dropDatabase($this->connection);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(static::$server)) {
            static::$server->stop();
        }
    }

    /**
     * @param 'pdo'|'doctrine'|'dbal'|'orm' $adapter
     * @param class-string $entity
     * @return array<array-key, mixed>
     */
    private function executeFilter(
        string $adapter,
        string $expression,
        string $table = 'products',
        string $entity = Product::class,
        string $field = 'id',
        int $offset = 0,
        ?int $limit = null,
        bool $distinct = false
    ): array {
        $condition = (new CompositeExpressionParser($this->parser))->parse($expression);
        if ($adapter === 'orm') {
            $qb = $this->em->createQueryBuilder()->select('p.' . $field . ' AS selected_key')->from($entity, 'p')
                ->distinct($distinct)->orderBy('p.' . $field)->setFirstResult($offset)->setMaxResults($limit);
            (new DoctrineORMQueryBuilderConditionApplier())->apply($qb, $condition);
            return $qb->getQuery()->getSingleColumnResult();
        }
        if ($adapter === 'dbal') {
            $qb = $this->connection->createQueryBuilder()->select('p.' . $field)->from($table, 'p')
                ->distinct($distinct)->orderBy('p.' . $field)->setFirstResult($offset)->setMaxResults($limit);
            (new DoctrineDBALQueryBuilderConditionApplier())->apply($qb, $condition);
            return $qb->fetchFirstColumn();
        }

        $pdo = $this->connection->getNativeConnection();
        $this->assertInstanceOf(PDO::class, $pdo);
        $engine = $adapter === 'pdo' ? new PdoEngine($pdo) : new DoctrineEngine($this->connection);
        $qb = (new SqlQueryBuilder($engine, $this->parser))->from($table, 'p')->select(['p.' . $field])
            ->where($condition)->distinct($distinct)->orderBy('p.' . $field)->offset($offset);
        if ($limit !== null) {
            $qb->limit($limit);
        }
        return array_map(static fn (array $row): mixed => array_values($row)[0], $qb->execute());
    }

    /** @return iterable<string, array{'pdo'|'doctrine'|'dbal'|'orm', string, list<int>}> */
    public static function commonFilters(): iterable
    {
        $cases = [
            'equality' => ['id?=1', [1]],
            'inequality' => ['id?!=1', [2, 3, 4, 5]],
            'decimal comparison' => ['price?>=20.50', [1, 2]],
            'decimal range' => ['price?between:0.70,5', [3, 4]],
            'list' => ['id?in:1,3,5', [1, 3, 5]],
            'excluded list' => ['id?notin:2,3,4', [1, 5]],
            'null' => ['price?is:null', [5]],
            'not null' => ['price?isnot:null', [1, 2, 3, 4]],
            'text' => ['category?=computing', [1, 2]],
            'leading zeros in a text identifier' => ['code?=00123', [1]],
            'injection is a literal value' => ["name?=x' OR 1=1 --", [5]],
            'quotes commas and wildcards as literal data' => ["name?=O'Reilly, 100%_safe", [4]],
            'like' => ['name?like:Lap%', [1]],
            'ordered dates' => ['created_at?>2026-10-02', [3, 4]],
            'composite and' => ['category?=office&&price?<1', [4]],
            'grouped or' => ['(id?=1||id?=3)&&price?>1', [1, 3]],
            'explicit alias' => ['p__code?=00123', [1]],
        ];
        foreach (['pdo', 'doctrine', 'dbal', 'orm'] as $adapter) {
            foreach ($cases as $name => [$expression, $expected]) {
                yield $adapter . ' ' . $name => [$adapter, $expression, $expected];
            }
        }
    }

    /** @param 'pdo'|'doctrine'|'dbal'|'orm' $adapter
     * @param list<int> $expected
     */
    #[DataProvider('commonFilters')]
    public function testFiltersExecuteAgainstTheRealServer(string $adapter, string $expression, array $expected): void
    {
        $this->assertSame($expected, $this->executeFilter($adapter, $expression));
    }

    /** @return iterable<string, array{'pdo'|'doctrine'|'dbal'|'orm'}> */
    public static function adapters(): iterable
    {
        foreach (['pdo', 'doctrine', 'dbal', 'orm'] as $adapter) {
            yield $adapter => [$adapter];
        }
    }

    /** @param 'pdo'|'doctrine'|'dbal'|'orm' $adapter */
    #[DataProvider('adapters')]
    public function testOrderingAndPaginationReturnTheRequestedPage(string $adapter): void
    {
        $this->assertSame([2, 3], $this->executeFilter($adapter, 'id?isnot:null', offset: 1, limit: 2));
    }

    /** @param 'pdo'|'doctrine'|'dbal'|'orm' $adapter */
    #[DataProvider('adapters')]
    public function testJoinsUseTheAliasAndDistinctRemovesDuplicateRootRows(string $adapter): void
    {
        $expression = 'products[alias:p]__invoice_details[alias:d,on:id=product_id]__quantity?>0';
        $this->assertSame([1, 2, 2, 3], $this->executeFilter($adapter, $expression));
        $this->assertSame([1, 2, 3], $this->executeFilter($adapter, $expression, distinct: true));
    }

    /** @return iterable<string, array{'pdo'|'doctrine'|'dbal'|'orm', string, list<int>}> */
    public static function invoiceSubqueries(): iterable
    {
        $cases = [
            'no related payments' => ['___payments[on:id=invoice_id]?is:empty', [12, 13, 14]],
            'related payments' => ['___payments[on:id=invoice_id]?isnot:empty', [10, 11]],
            'filtered payments' => ['___payments[on:id=invoice_id]__status?=pending', [11]],
            'sum' => ['___payments[on:id=invoice_id]__SUM(amount)?>=20.50', [10, 11]],
            'average' => ['___payments[on:id=invoice_id]__AVG(amount)?<20', [11]],
            'count' => ['___payments[on:id=invoice_id]__COUNT(*)?>1', [11]],
            'fractional count threshold' => ['___payments[on:id=invoice_id]__COUNT(*)?>1.5', [11]],
            'minimum' => ['___payments[on:id=invoice_id]__MIN(amount)?=10', [11]],
            'maximum' => ['___payments[on:id=invoice_id]__MAX(amount)?=100.25', [10]],
        ];
        foreach (['pdo', 'doctrine', 'dbal', 'orm'] as $adapter) {
            foreach ($cases as $name => [$expression, $expected]) {
                yield $adapter . ' ' . $name => [$adapter, $expression, $expected];
            }
        }
    }

    /** @param 'pdo'|'doctrine'|'dbal'|'orm' $adapter
     * @param list<int> $expected
     */
    #[DataProvider('invoiceSubqueries')]
    public function testCorrelatedSubqueriesExecuteAgainstTheRealServer(string $adapter, string $expression, array $expected): void
    {
        $this->assertSame($expected, $this->executeFilter($adapter, $expression, 'invoices', Invoice::class));
    }

    /** @param 'pdo'|'doctrine'|'dbal'|'orm' $adapter */
    #[DataProvider('adapters')]
    public function testNestedExistsKeepsTheCustomerCorrelation(string $adapter): void
    {
        $expression = '___invoices[on:id=customer_id]___payments[on:id=invoice_id]__status?=pending';
        $this->assertSame([1], $this->executeFilter($adapter, $expression, 'customers', Customer::class));
    }

    /** @return iterable<string, array{class-string, string, string, list<string|int>}> */
    public static function associationCases(): iterable
    {
        yield from DoctrineORMAssociationSubqueryTest::associations();
        foreach (DoctrineORMAssociationSubqueryTest::typedAggregates() as $name => [$expression, $expected]) {
            yield $name => [Account::class, 'accountKey', $expression, $expected];
        }
    }

    /** @param class-string $entity
     * @param list<string|int> $expected
     */
    #[DataProvider('associationCases')]
    public function testOrmAssociationsAndMappedAggregateTypes(string $entity, string $field, string $expression, array $expected): void
    {
        $this->assertSame($expected, $this->executeFilter('orm', $expression, entity: $entity, field: $field));
    }

    /** @return iterable<string, array{'pdo'|'doctrine'|'dbal', string, list<int>}> */
    public static function sqlRegexOperators(): iterable
    {
        foreach (['pdo', 'doctrine', 'dbal'] as $adapter) {
            yield $adapter . ' sensitive' => [$adapter, 'name?~^Lap', [1]];
            yield $adapter . ' insensitive' => [$adapter, 'name?~*^lap', [1]];
        }
    }

    /** @param 'pdo'|'doctrine'|'dbal' $adapter
     * @param list<int> $expected
     */
    #[DataProvider('sqlRegexOperators')]
    public function testSqlRegexOperatorsUseTheDatabaseDialect(string $adapter, string $expression, array $expected): void
    {
        $this->assertSame($expected, $this->executeFilter($adapter, $expression));
    }

    public function testOrmRejectsSqlOnlyOperatorsBeforeExecutingTheQuery(): void
    {
        $this->expectException(UnsupportedOperatorException::class);
        $this->executeFilter('orm', 'name?~^Lap');
    }

    /** @return iterable<string, array{bool, bool}> */
    public static function configurationLoaders(): iterable
    {
        foreach ([false, true] as $yaml) {
            foreach ([false, true] as $pdo) {
                yield ($yaml ? 'yaml' : 'json') . ($pdo ? ' pdo' : ' doctrine') => [$yaml, $pdo];
            }
        }
    }

    #[DataProvider('configurationLoaders')]
    public function testLoadedConfigurationExecutesTheConfiguredQuery(bool $yaml, bool $pdo): void
    {
        $input = $yaml
            ? "table: products\nalias: p\nselect: [p.id]\nwhere: 'price?>=20.50'\norderBy: [p.id]\n"
            : '{"table":"products","alias":"p","select":["p.id"],"where":"price?>=20.50","orderBy":["p.id"]}';
        $config = ($yaml ? new YamlConfigLoader() : new JsonConfigLoader())->loadFromString($input);
        $native = $this->connection->getNativeConnection();
        $this->assertInstanceOf(PDO::class, $native);
        $engine = $pdo ? new PdoEngine($native) : new DoctrineEngine($this->connection);
        $builder = (new QueryConfig($config))->applyTo(new SqlQueryBuilder($engine, $this->parser));
        $this->assertSame([['id' => 1], ['id' => 2]], $builder->execute());
    }

    /** @param 'pdo'|'doctrine'|'dbal'|'orm' $adapter */
    #[DataProvider('adapters')]
    public function testDecimalAggregateThresholdsKeepTheirMeaning(string $adapter): void
    {
        $cases = [
            ['___payments[on:id=invoice_id]__COUNT(*)?=1.5', []],
            ['___payments[on:id=invoice_id]__COUNT(*)?in:1.5,2', [11]],
            ['___payments[on:id=invoice_id]__COUNT(*)?between:1.5,2.5', [11]],
            ['___payments[on:id=invoice_id]__COUNT(*)?notbetween:0.5,1.5', [11, 12, 13, 14]],
            ['___payments[on:id=invoice_id]__COUNT(*)?<-0.5', []],
            ['___invoice_details[on:id=invoice_id]__SUM(quantity)?>1.5', [10, 11, 12]],
            ['___payments[on:id=invoice_id]__AVG(amount)?between:10.20,10.30', [11]],
            ['___payments[on:id=invoice_id]__SUM(amount)?is:null', [12, 13, 14]],
        ];
        foreach ($cases as [$expression, $expected]) {
            $this->assertSame(
                $expected,
                $this->executeFilter($adapter, $expression, 'invoices', Invoice::class),
                $expression
            );
        }
    }

    /** @param 'pdo'|'doctrine'|'dbal'|'orm' $adapter */
    #[DataProvider('adapters')]
    public function testLikePatternsAndInheritedOperatorsExecuteConsistently(string $adapter): void
    {
        $cases = [
            ['name?like:lap%', []],
            ['name?like:L_p%', [1]],
            ['name?notlike:Lap%', [2, 3, 4, 5]],
            ['name?^Lap', [1]],
            ['name?!^Lap', [2, 3, 4, 5]],
            ['name?~~apt', [1]],
            ['name?!~~apt', [2, 3, 4, 5]],
            ['name?$top', [1]],
            ['name?!$top', [2, 3, 4, 5]],
            ['category?notlike:comp%', [3, 4]],
            ['code?Elike:p.code', [1, 2, 3, 4, 5]],
            ['code?Enotlike:p.code', []],
        ];
        foreach ($cases as [$expression, $expected]) {
            $this->assertSame($expected, $this->executeFilter($adapter, $expression), $expression);
        }
    }

    public function testOrmLikeTemplatesWorkInSubqueriesAndHaving(): void
    {
        foreach ([
            '___payments[on:id=invoice_id]__status?like:comp%' => [10, 11],
            '___payments[on:id=invoice_id]__MIN(status)?like:comp%' => [10, 11],
            '___payments[on:id=invoice_id]__MIN(status)?notlike:pend%' => [10, 11],
        ] as $expression => $expected) {
            $this->assertSame($expected, $this->executeFilter('orm', $expression, 'invoices', Invoice::class));
        }

        $qb = $this->em->createQueryBuilder()->select('p.category')->from(Product::class, 'p')
            ->groupBy('p.category');
        (new DoctrineORMQueryBuilderConditionApplier())->applyHaving($qb, $this->parser->parse('category?like:comp%'));
        $this->assertSame(['computing'], $qb->getQuery()->getSingleColumnResult());
    }

    /** @param 'pdo'|'doctrine'|'dbal' $adapter */
    #[DataProvider('sqlAdapters')]
    public function testRegexSensitivityNegationAliasesAndNulls(string $adapter): void
    {
        $cases = [
            ['name?~^lap', []],
            ['name?~*^lap', [1]],
            ['name?!~^Lap', [2, 3, 4, 5]],
            ['name?!~*^lap', [2, 3, 4, 5]],
            ['name?regexp:^Lap', [1]],
            ['name?rlike:^lap', []],
            ['name?notregexp:^Lap', [2, 3, 4, 5]],
            ['name?notrlike:^Lap', [2, 3, 4, 5]],
            ['category?~^comp', [1, 2]],
            ['category?!~^comp', [3, 4]],
            ['category?!~*^COMP', [3, 4]],
        ];
        foreach ($cases as [$expression, $expected]) {
            $this->assertSame($expected, $this->executeFilter($adapter, $expression), $expression);
        }
    }

    /** @return iterable<string, array{'pdo'|'doctrine'|'dbal'}> */
    public static function sqlAdapters(): iterable
    {
        foreach (['pdo', 'doctrine', 'dbal'] as $adapter) {
            yield $adapter => [$adapter];
        }
    }
}
