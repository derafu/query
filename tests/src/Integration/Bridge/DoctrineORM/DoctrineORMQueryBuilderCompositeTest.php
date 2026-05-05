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

use Derafu\Query\Bridge\DoctrineORMQueryBuilderConditionApplier;
use Derafu\Query\Builder\Sql\SqlBuilderWhere;
use Derafu\Query\Builder\Sql\SqlQuery;
use Derafu\Query\Filter\CompositeCondition;
use Derafu\Query\Filter\CompositeExpressionParser;
use Derafu\Query\Filter\Condition;
use Derafu\Query\Filter\Contract\CompositeExpressionParserInterface;
use Derafu\Query\Filter\ExpressionParser;
use Derafu\Query\Filter\Filter;
use Derafu\Query\Filter\FilterParser;
use Derafu\Query\Filter\Path;
use Derafu\Query\Filter\PathParser;
use Derafu\Query\Filter\Segment;
use Derafu\Query\Operator\Operator;
use Derafu\Query\Operator\OperatorLoader;
use Derafu\Query\Operator\OperatorManager;
use Doctrine\DBAL\Connection;
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

#[CoversClass(CompositeExpressionParser::class)]
#[UsesClass(DoctrineORMQueryBuilderConditionApplier::class)]
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
class DoctrineORMQueryBuilderCompositeTest extends TestCase
{
    private Connection $connection;

    private EntityManager $em;

    private DoctrineORMQueryBuilderConditionApplier $applier;

    private CompositeExpressionParserInterface $compositeParser;

    protected function setUp(): void
    {
        $cache = new ArrayCachePool();
        $config = ORMSetup::createConfig(false, null, $cache);
        $config->setMetadataDriverImpl(new AttributeDriver([__DIR__ . '/Entity']));
        $config->enableNativeLazyObjects(true);

        $this->connection = DriverManager::getConnection(
            ['driver' => 'pdo_sqlite', 'memory' => true],
            $config
        );

        $this->em = new EntityManager($this->connection, $config);

        $pdo = $this->connection->getNativeConnection();
        assert($pdo instanceof PDO);
        $pdo->exec(file_get_contents(
            __DIR__ . '/../../../../fixtures/integration/billing_schema.sql'
        ));
        $pdo->exec(file_get_contents(
            __DIR__ . '/../../../../fixtures/integration/billing_data.sql'
        ));

        $pathParser = new PathParser();
        $loader = new OperatorLoader();
        $operators = $loader->loadFromFile(
            __DIR__ . '/../../../../../resources/operators.yaml'
        );
        $manager = new OperatorManager($operators);
        $filterParser = new FilterParser($manager);
        $expressionParser = new ExpressionParser($pathParser, $filterParser);

        $this->compositeParser = new CompositeExpressionParser($expressionParser);
        $this->applier = new DoctrineORMQueryBuilderConditionApplier();
    }

    #[DataProvider('compositeQueryProvider')]
    public function testCompositeQueries(
        string $description,
        array $sql,
        array $query
    ): void {
        $expected = $this->connection->fetchAllAssociative($sql['sql'], $sql['parameters']);
        $qb = $this->buildQueryFromConfig($query);
        $actual = $qb->getQuery()->getScalarResult();

        $expected = $this->normalizeResults($expected);
        $actual = $this->normalizeResults($actual);

        $this->assertSame($expected, $actual, $description);
    }

    public static function compositeQueryProvider(): array
    {
        $cases = require __DIR__ . '/../../../../fixtures/integration/doctrine_orm.php';
        $data = [];

        foreach ($cases['composite_cases'] as $name => $case) {
            $data[$name] = [
                $case['description'],
                $case['sql'],
                $case['query'],
            ];
        }

        return $data;
    }

    private function normalizeResults(array $results): array
    {
        $normalized = array_map(
            fn (array $row) => array_map(
                fn ($v) => $v === null ? null : (string) $v,
                $row
            ),
            $results
        );

        usort($normalized, fn ($a, $b) => json_encode($a) <=> json_encode($b));

        return $normalized;
    }

    private function buildQueryFromConfig(array $config): DoctrineORMQueryBuilder
    {
        $qb = $this->em->createQueryBuilder();

        if (isset($config['table'])) {
            $alias = $config['alias'] ?? 'e';
            $qb->from($config['table'], $alias);
        }

        if (isset($config['select'])) {
            $qb->select($config['select']);
        }

        if (isset($config['composite'])) {
            $parsed = $this->compositeParser->parse($config['composite']);
            $this->applier->apply($qb, $parsed);
        }

        return $qb;
    }
}
