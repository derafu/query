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

use Derafu\Array\ArrayReader;
use Derafu\Query\Bridge\DoctrineORMQueryBuilderConditionApplier;
use Derafu\Query\Builder\AliasScope;
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
use Derafu\Value\ValueType;
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

#[UsesClass(AliasScope::class)]
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
        $sql = file_get_contents(
            __DIR__ . '/../../../../fixtures/integration/billing_schema.sql'
        );
        $this->assertIsString($sql);
        $pdo->exec($sql);
        $sql = file_get_contents(
            __DIR__ . '/../../../../fixtures/integration/billing_data.sql'
        );
        $this->assertIsString($sql);
        $pdo->exec($sql);

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

    /**
     * @param array{sql:string,parameters:array<string,mixed>} $sql
     * @param ArrayReader $query
     */
    #[DataProvider('compositeQueryProvider')]
    public function testCompositeQueries(
        string $description,
        array $sql,
        ArrayReader $query
    ): void {
        $expected = $this->connection->fetchAllAssociative($sql['sql'], $sql['parameters']);
        $qb = $this->buildQueryFromConfig($query);
        $actual = $qb->getQuery()->getScalarResult();

        $expected = $this->normalizeResults($expected);
        $actual = $this->normalizeResults($actual);

        $this->assertSame($expected, $actual, $description);
    }

    /**
     * @return array<string,array{string,array{sql:string,parameters:array<string,mixed>},ArrayReader}>
     */
    public static function compositeQueryProvider(): array
    {
        $cases = ArrayReader::fromValue(require __DIR__ . '/../../../../fixtures/integration/doctrine_orm.php');
        $data = [];

        foreach ($cases->requireMapOf('composite_cases', ValueType::array()) as $name => $case) {
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
     * @param array<mixed> $results
     * @return array<array<string|int,string|null>>
     */
    private function normalizeResults(array $results): array
    {
        $normalized = array_map(
            function (mixed $row): array {
                $this->assertIsArray($row);

                return array_map(
                    function (mixed $value): ?string {
                        if ($value === null) {
                            return null;
                        }
                        $this->assertIsScalar($value);

                        return (string) $value;
                    },
                    $row
                );
            },
            $results
        );

        usort($normalized, fn ($a, $b) => json_encode($a) <=> json_encode($b));

        return $normalized;
    }

    /**
     * @param ArrayReader $config
     */
    private function buildQueryFromConfig(ArrayReader $config): DoctrineORMQueryBuilder
    {
        $qb = $this->em->createQueryBuilder();

        if ($config->hasValue('table')) {
            $alias = $config->getString('alias') ?? 'e';
            $qb->from($config->require('table', ValueType::classString()), $alias);
        }

        if ($config->hasValue('select')) {
            $qb->select($config->requireString('select'));
        }

        if ($config->hasValue('composite')) {
            $parsed = $this->compositeParser->parse($config->requireString('composite'));
            $this->applier->apply($qb, $parsed);
        }

        return $qb;
    }
}
