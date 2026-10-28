<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsQuery\Integration;

use Derafu\Query\Bridge\Contract\QueryBuilderConditionApplierInterface;
use Derafu\Query\Builder\Contract\QueryBuilderInterface;
use Derafu\Query\Builder\Contract\QueryInterface;
use Derafu\Query\Engine\Contract\SqlEngineInterface;
use Derafu\Query\Filter\Contract\CompositeConditionInterface;
use Derafu\Query\Filter\Contract\CompositeExpressionParserInterface;
use Derafu\Query\Filter\Contract\ExpressionParserInterface;
use Derafu\Query\Filter\Contract\FilterParserInterface;
use Derafu\Query\Filter\Contract\PathParserInterface;
use Derafu\Query\Translation\QueryTranslationResourceProvider;
use Derafu\TestsQuery\Integration\Bridge\DoctrineORM\Entity\Product;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Database\ConnectionInterface as IlluminateConnectionInterface;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

/**
 * Executes real queries through production YAML and consumer-defined connections.
 */
#[CoversNothing]
final class ServicesTest extends TestCase
{
    private function container(string $configuration = 'services.yaml'): ContainerBuilder
    {
        $root = dirname(__DIR__, 3);
        $container = new ContainerBuilder();
        $container->setParameter('fixture.query.root', $root);
        $loader = new YamlFileLoader($container, new FileLocator($root . '/tests/fixtures/config'));
        $loader->load($configuration);
        $this->assertArrayHasKey(
            QueryTranslationResourceProvider::class,
            $container->findTaggedServiceIds('derafu_translation.resource_provider')
        );
        foreach ($container->getDefinitions() as $definition) {
            $definition->setPublic(true);
        }
        $container->compile();

        return $container;
    }

    public function testConfigurationConstructsTheTranslationProvider(): void
    {
        $provider = $this->container()->get(QueryTranslationResourceProvider::class);
        $this->assertInstanceOf(QueryTranslationResourceProvider::class, $provider);
        $directories = [...$provider->getDirectories()];
        $this->assertCount(1, $directories);
        $this->assertSame(realpath(dirname(__DIR__, 3) . '/resources/translations'), realpath($directories[0]));
    }

    public function testTaggedResourcesTranslateARealDuplicateAliasException(): void
    {
        $translator = $this->container()->get('app.translator');
        $this->assertInstanceOf(Translator::class, $translator);
        try {
            $parser = $this->container()->get(PathParserInterface::class);
            $this->assertInstanceOf(PathParserInterface::class, $parser);
            $parser->parse('products[alias:p,alias:q]__name');
            $this->fail('A repeated alias option was accepted.');
        } catch (TranslatableInvalidArgumentException $exception) {
            $this->assertSame('La opción "alias" está definida más de una vez.', $exception->trans($translator));
        }
    }

    private function seed(PDO $pdo): void
    {
        foreach (['billing_schema.sql', 'billing_data.sql'] as $name) {
            $sql = file_get_contents(dirname(__DIR__, 2) . '/fixtures/integration/' . $name);
            $this->assertIsString($sql);
            $this->assertNotFalse($pdo->exec($sql));
        }
    }

    public function testCoreConfigurationParsesFiltersWithoutChoosingADatabase(): void
    {
        $container = $this->container();
        $parser = $container->get(CompositeExpressionParserInterface::class);
        $this->assertInstanceOf(CompositeExpressionParserInterface::class, $parser);
        $condition = $parser->parse('price?>=250&&category?=electronics');
        $this->assertInstanceOf(CompositeConditionInterface::class, $condition);
        $this->assertCount(2, $condition->getConditions());
        $this->assertFalse($container->has(SqlEngineInterface::class));
        $this->assertFalse($container->has(QueryBuilderInterface::class));
        $this->assertFalse($container->has(QueryBuilderConditionApplierInterface::class));
    }

    /** @return iterable<string, array{string}> */
    public static function sqlEngines(): iterable
    {
        yield 'pdo' => ['engine-pdo.yaml'];
        yield 'doctrine dbal' => ['engine-doctrine.yaml'];
    }

    #[DataProvider('sqlEngines')]
    public function testOptionalEngineExecutesBoundQueriesAndKeepsBuildersIndependent(string $configuration): void
    {
        $container = $this->container($configuration);
        $engine = $container->get(SqlEngineInterface::class);
        $this->assertInstanceOf(SqlEngineInterface::class, $engine);
        $connection = $engine->getConnection();
        $pdo = $connection instanceof Connection ? $connection->getNativeConnection() : $connection;
        $this->assertInstanceOf(PDO::class, $pdo);
        $this->seed($pdo);
        $first = $container->get(QueryBuilderInterface::class);
        $second = $container->get(QueryBuilderInterface::class);
        $this->assertInstanceOf(QueryBuilderInterface::class, $first);
        $this->assertInstanceOf(QueryBuilderInterface::class, $second);
        $this->assertNotSame($first, $second);
        $first->from('products')->select('id')->where('price?>=250')->orderBy('id');
        $query = $first->getQuery();
        $this->assertInstanceOf(QueryInterface::class, $query);
        $sql = $query->getQuery();
        $this->assertIsArray($sql);
        $this->assertIsArray($sql['parameters']);
        $this->assertCount(1, $sql['parameters']);
        $this->assertIsString($sql['sql']);
        $this->assertStringNotContainsString('250', $sql['sql']);
        $rows = $first->execute();
        $this->assertIsArray($rows);
        $this->assertSame([1, 2, 5, 8], array_column($rows, 'id'));
        $second->from('products')->select('id')->where('price?<10');
        $rows = $second->execute();
        $this->assertIsArray($rows);
        $this->assertSame([4], array_column($rows, 'id'));
        $rows = $first->execute();
        $this->assertIsArray($rows);
        $this->assertSame([1, 2, 5, 8], array_column($rows, 'id'));
    }

    public function testOperatorFileOverrideChangesTheParserAndExecutedQuery(): void
    {
        $container = $this->container('custom-operators.yaml');
        $pdo = $container->get(PDO::class);
        $this->assertInstanceOf(PDO::class, $pdo);
        $this->seed($pdo);
        $parser = $container->get(FilterParserInterface::class);
        $this->assertInstanceOf(FilterParserInterface::class, $parser);
        $this->assertSame('same:', $parser->parse('same:Business Laptop')->getOperator()->getSymbol());
        $builder = $container->get(QueryBuilderInterface::class);
        $this->assertInstanceOf(QueryBuilderInterface::class, $builder);
        $rows = $builder->from('products')->select('id')->where('name?same:Business Laptop')->execute();
        $this->assertIsArray($rows);
        $this->assertSame([1], array_column($rows, 'id'));
    }

    public function testDbalBridgeAppliesTheConfiguredCompositeParserToARealQuery(): void
    {
        $container = $this->container('bridge-dbal.yaml');
        $connection = $container->get(Connection::class);
        $this->assertInstanceOf(Connection::class, $connection);
        $pdo = $connection->getNativeConnection();
        $this->assertInstanceOf(PDO::class, $pdo);
        $this->seed($pdo);
        $parser = $container->get(CompositeExpressionParserInterface::class);
        $this->assertInstanceOf(CompositeExpressionParserInterface::class, $parser);
        $applier = $container->get(QueryBuilderConditionApplierInterface::class);
        $this->assertInstanceOf(QueryBuilderConditionApplierInterface::class, $applier);
        $query = $connection->createQueryBuilder()->select('id')->from('products')->orderBy('id');
        $applier->apply($query, $parser->parse('price?>=250&&category?=electronics'));
        $this->assertCount(2, $query->getParameters());
        $this->assertSame([1, 2], $query->executeQuery()->fetchFirstColumn());
    }

    public function testOrmBridgeAppliesTheSharedParserToMappedEntities(): void
    {
        $container = $this->container('bridge-orm.yaml');
        $manager = $container->get(EntityManagerInterface::class);
        $this->assertInstanceOf(EntityManagerInterface::class, $manager);
        $pdo = $manager->getConnection()->getNativeConnection();
        $this->assertInstanceOf(PDO::class, $pdo);
        $this->seed($pdo);
        $parser = $container->get(ExpressionParserInterface::class);
        $this->assertInstanceOf(ExpressionParserInterface::class, $parser);
        $applier = $container->get(QueryBuilderConditionApplierInterface::class);
        $this->assertInstanceOf(QueryBuilderConditionApplierInterface::class, $applier);
        $query = $manager->createQueryBuilder()->select('p.id')->from(Product::class, 'p')->orderBy('p.id');
        $applier->apply($query, $parser->parse('price?>=250'));
        $this->assertCount(1, $query->getParameters());
        $this->assertSame([1, 2, 5, 8], array_column($query->getQuery()->getArrayResult(), 'id'));
    }

    public function testIlluminateBridgeAppliesTheSharedParserToARealQuery(): void
    {
        $container = $this->container('bridge-illuminate.yaml');
        $pdo = $container->get(PDO::class);
        $this->assertInstanceOf(PDO::class, $pdo);
        $this->seed($pdo);
        $connection = $container->get(IlluminateConnectionInterface::class);
        $this->assertInstanceOf(IlluminateConnectionInterface::class, $connection);
        $parser = $container->get(ExpressionParserInterface::class);
        $this->assertInstanceOf(ExpressionParserInterface::class, $parser);
        $applier = $container->get(QueryBuilderConditionApplierInterface::class);
        $this->assertInstanceOf(QueryBuilderConditionApplierInterface::class, $applier);
        $query = $connection->table('products')->select('id')->orderBy('id');
        $applier->apply($query, $parser->parse('price?>=250'));
        $this->assertCount(1, $query->getBindings());
        $this->assertSame([1, 2, 5, 8], $query->pluck('id')->all());
    }
}
