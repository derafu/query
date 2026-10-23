<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsQuery\Functional\Builder;

use Derafu\Query\Builder\AliasScope;
use Derafu\Query\Builder\Sql\SqlBuilderWhere;
use Derafu\Query\Builder\Sql\SqlQuery;
use Derafu\Query\Filter\Condition;
use Derafu\Query\Filter\Filter;
use Derafu\Query\Filter\Path;
use Derafu\Query\Filter\PathParser;
use Derafu\Query\Filter\Segment;
use Derafu\Query\Operator\Operator;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[UsesClass(AliasScope::class)]
#[CoversClass(SqlBuilderWhere::class)]
#[UsesClass(SqlQuery::class)]
#[UsesClass(Condition::class)]
#[UsesClass(Filter::class)]
#[UsesClass(Path::class)]
#[UsesClass(PathParser::class)]
#[UsesClass(Segment::class)]
#[UsesClass(Operator::class)]
final class OperatorSqlTemplateTest extends TestCase
{
    // SQLite aggregate expressions have no column affinity, so this numeric
    // template explicitly casts the bound value for consistent comparisons.
    private const TEMPLATE = '{{column}} > CAST({{value}} AS INTEGER)';

    private const PATHS = [
        'normal' => 'p__id',
        'exists' => '___stock[on:id=product_id]__quantity',
        'aggregate' => '___stock[on:id=product_id]__SUM(quantity)',
    ];

    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $sql = file_get_contents(__DIR__ . '/../../../fixtures/functional/query_config.sql');
        $this->assertIsString($sql);
        $this->pdo->exec($sql);
    }

    /**
     * @return array{sql:string,parameters:array<string,mixed>}
     */
    private function compile(string $path, Operator $operator, bool $dql = false): array
    {
        $condition = new Condition((new PathParser())->parse($path), new Filter($operator, '1'));

        return (new SqlBuilderWhere('sqlite', dql: $dql))->build($condition, 'p')->getQuery();
    }

    /**
     * @return list<int>
     */
    private function execute(string $path, Operator $operator, bool $dql = false): array
    {
        $query = $this->compile($path, $operator, $dql);
        $statement = $this->pdo->prepare('SELECT p.id FROM products p WHERE ' . $query['sql'] . ' ORDER BY p.id');
        $statement->execute($query['parameters']);
        $result = [];
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $this->assertIsInt($id);
            $result[] = $id;
        }

        return $result;
    }

    /**
     * @return iterable<string,array{string,mixed}>
     */
    public static function invalidTemplates(): iterable
    {
        foreach (self::PATHS as $route => $path) {
            foreach ([false, 123, ['sqlite' => []], ['sqlite' => 123], [0 => self::TEMPLATE], ['sqlite' => self::TEMPLATE, 'mysql' => false]] as $index => $sql) {
                yield $route . ': invalid template ' . $index => [$path, $sql];
            }
        }
    }

    #[DataProvider('invalidTemplates')]
    public function testRejectsInvalidTemplates(string $path, mixed $sql): void
    {
        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('sql');
        $this->compile($path, new Operator('>', ['sql' => $sql]));
    }

    public function testUniversalAndEngineTemplatesWorkInAllThreeRoutes(): void
    {
        foreach (self::PATHS as $route => $path) {
            foreach ([self::TEMPLATE, ['sqlite' => self::TEMPLATE, 'pgsql' => self::TEMPLATE]] as $sql) {
                $this->assertSame(
                    $route === 'normal' ? [2, 3, 4] : [1],
                    $this->execute($path, new Operator('>', ['sql' => $sql]))
                );
            }
        }
    }

    public function testAbsentAndNullSqlInheritTheBaseTemplate(): void
    {
        $base = new Operator('>', ['sql' => ['sqlite' => self::TEMPLATE]]);
        foreach ([[], ['sql' => null]] as $config) {
            foreach (self::PATHS as $route => $path) {
                $this->assertSame(
                    $route === 'normal' ? [2, 3, 4] : [1],
                    $this->execute($path, new Operator('custom:', $config, $base))
                );
            }
        }
    }

    public function testOwnTemplateOverridesBaseTemplate(): void
    {
        $base = new Operator('>', ['sql' => self::TEMPLATE]);
        $operator = new Operator('custom:', ['sql' => '{{column}} = {{value}}'], $base);
        $this->assertSame([1], $this->execute(self::PATHS['normal'], $operator));
    }

    /**
     * @return iterable<string,array{array<string,mixed>}>
     */
    public static function unavailableTemplates(): iterable
    {
        yield 'absent' => [[]];
        yield 'null' => [['sql' => null]];
        yield 'empty string' => [['sql' => '']];
        yield 'empty map' => [['sql' => []]];
        yield 'different engine' => [['sql' => ['pgsql' => self::TEMPLATE]]];
    }

    /**
     * @param array<string,mixed> $config
     */
    #[DataProvider('unavailableTemplates')]
    public function testNormalConditionsRequireAnAvailableTemplate(array $config): void
    {
        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('No SQL template');
        $this->compile(self::PATHS['normal'], new Operator('>', $config));
    }

    /**
     * @param array<string,mixed> $config
     */
    #[DataProvider('unavailableTemplates')]
    public function testAggregateConditionsRequireAnAvailableTemplate(array $config): void
    {
        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('No SQL template');
        $this->compile(self::PATHS['aggregate'], new Operator('>', $config));
    }

    /**
     * @param array<string,mixed> $config
     */
    #[DataProvider('unavailableTemplates')]
    public function testExistsWithoutATemplateKeepsOnlyTheCorrelation(array $config): void
    {
        $this->assertSame([1, 2], $this->execute(self::PATHS['exists'], new Operator('>', $config)));
    }

    public function testOwnMapWithoutTheEngineDoesNotFallBackToTheBase(): void
    {
        $base = new Operator('>', ['sql' => self::TEMPLATE]);
        $operator = new Operator('custom:', ['sql' => ['pgsql' => self::TEMPLATE]], $base);
        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('No SQL template');
        $this->compile(self::PATHS['normal'], $operator);
    }

    public function testVariantsSelectTheLanguageAndInheritInAllRoutes(): void
    {
        $base = new Operator('>', ['sql' => ['sqlite' => [
            'default' => self::TEMPLATE,
            'dql' => '{{column}} < CAST({{value}} AS INTEGER)',
        ]]]);
        foreach (self::PATHS as $route => $path) {
            foreach ([$base, new Operator('custom:', [], $base), new Operator('custom:', ['sql' => null], $base)] as $operator) {
                $this->assertSame($route === 'normal' ? [2, 3, 4] : [1], $this->execute($path, $operator));
                $this->assertSame($route === 'normal' ? [] : [2], $this->execute($path, $operator, true));
            }
        }
    }

    public function testDqlFallsBackToDefaultAndScalarTemplates(): void
    {
        foreach ([self::TEMPLATE, ['sqlite' => self::TEMPLATE], ['sqlite' => ['default' => self::TEMPLATE]]] as $sql) {
            foreach (self::PATHS as $route => $path) {
                $this->assertSame(
                    $route === 'normal' ? [2, 3, 4] : [1],
                    $this->execute($path, new Operator('>', ['sql' => $sql]), true)
                );
            }
        }
    }

    /** @return iterable<string, array{string, mixed, bool}> */
    public static function invalidVariants(): iterable
    {
        $variants = [
            'missing default' => ['dql' => self::TEMPLATE],
            'null default' => ['default' => null],
            'numeric default' => ['default' => 1],
            'nested default' => ['default' => ['default' => self::TEMPLATE]],
            'null dql' => ['default' => self::TEMPLATE, 'dql' => null],
            'boolean dql' => ['default' => self::TEMPLATE, 'dql' => false],
            'nested dql' => ['default' => self::TEMPLATE, 'dql' => ['default' => self::TEMPLATE]],
            'unknown variant' => ['default' => self::TEMPLATE, 'other' => self::TEMPLATE],
            'numeric key' => ['default' => self::TEMPLATE, self::TEMPLATE],
        ];
        foreach (self::PATHS as $route => $path) {
            foreach ([false, true] as $dql) {
                foreach ($variants as $name => $variant) {
                    yield $route . ($dql ? ' dql ' : ' sql ') . $name => [$path, ['sqlite' => $variant], $dql];
                    yield $route . ($dql ? ' dql ' : ' sql ') . $name . ' unselected engine' => [
                        $path, ['sqlite' => self::TEMPLATE, 'mysql' => $variant], $dql,
                    ];
                }
            }
        }
    }

    #[DataProvider('invalidVariants')]
    public function testRejectsInvalidVariantsEvenWhenNotSelected(string $path, mixed $sql, bool $dql): void
    {
        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('sql');
        $this->compile($path, new Operator('>', ['sql' => $sql]), $dql);
    }
}
