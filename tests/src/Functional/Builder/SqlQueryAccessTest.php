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

use Derafu\Query\Builder\Sql\SqlQuery;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use LogicException;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SplFileInfo;
use stdClass;

#[CoversClass(SqlQuery::class)]
final class SqlQueryAccessTest extends TestCase
{
    /**
     * @return iterable<string,array{mixed,string}>
     */
    public static function invalidKeys(): iterable
    {
        yield 'string' => ['missing', 'missing'];
        yield 'integer' => [1234567, '1234567'];
        yield 'float' => [42.5, '42.5'];
        yield 'true' => [true, '1'];
        yield 'false' => [false, ''];
        yield 'null' => [null, ''];
        yield 'empty array' => [[], 'array'];
        yield 'nested array' => [['sql' => ['parameters']], 'array'];
        yield 'object' => [new stdClass(), 'stdClass'];
        yield 'stringable' => [new SplFileInfo('missing'), 'missing'];
    }

    #[DataProvider('invalidKeys')]
    public function testInvalidKeysProduceTheTranslatedException(mixed $key, string $description): void
    {
        $query = new SqlQuery('SELECT 1', []);
        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('Key ' . $description . ' does not exists.');
        $query->offsetGet($key);
    }

    public function testResourceKeysKeepTheirExistingRepresentation(): void
    {
        $stream = fopen('php://memory', 'r+');
        $this->assertIsResource($stream);
        $query = new SqlQuery('SELECT 1', []);
        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('Key ' . (string) $stream . ' does not exists.');
        try {
            $query->offsetGet($stream);
        } finally {
            fclose($stream);
        }
    }

    public function testValidKeysRetainSqlAndBindingsForExecution(): void
    {
        $query = new SqlQuery('SELECT :value AS value', ['value' => 'bound value']);
        $this->assertSame(['sql' => 'SELECT :value AS value', 'parameters' => ['value' => 'bound value']], $query->getQuery());
        $pdo = new PDO('sqlite::memory:');
        $statement = $pdo->prepare($query['sql']);
        $this->assertNotFalse($statement);
        $statement->execute($query['parameters']);
        $this->assertSame(['value' => 'bound value'], $statement->fetch(PDO::FETCH_ASSOC));
    }

    public function testQueryFieldsCanBeCheckedButNotRemoved(): void
    {
        $query = new SqlQuery('SELECT :value', ['value' => 'preserved']);
        $this->assertTrue(isset($query['sql']));
        $this->assertTrue(isset($query['parameters']));
        $this->assertFalse(isset($query['missing']));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('SQL Query data is immutable.');
        try {
            unset($query['parameters']);
        } finally {
            $this->assertSame(['value' => 'preserved'], $query['parameters']);
        }
    }
}
