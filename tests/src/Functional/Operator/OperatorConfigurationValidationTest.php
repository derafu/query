<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsQuery\Functional\Operator;

use Derafu\Query\Operator\Operator;
use Derafu\Query\Operator\OperatorLoader;
use Derafu\Query\Operator\OperatorManager;
use Derafu\Translation\Exception\Core\TranslatableRuntimeException;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(Operator::class)]
#[CoversClass(OperatorLoader::class)]
#[CoversClass(OperatorManager::class)]
final class OperatorConfigurationValidationTest extends TestCase
{
    /**
     * @return array<string,mixed>
     */
    private static function definition(): array
    {
        return ['type' => 'standard', 'name' => 'Equals', 'description' => 'Equality'];
    }

    /**
     * @return iterable<string,array{array<array-key,mixed>}>
     */
    public static function invalidStructures(): iterable
    {
        yield 'types is a scalar' => [['types' => 'standard', 'operators' => []]];
        yield 'operators is a scalar' => [['types' => [], 'operators' => '=']];
        yield 'definition is a scalar' => [['types' => [], 'operators' => ['=' => 'invalid']]];
        yield 'symbol is numeric' => [['types' => [], 'operators' => [123 => self::definition()]]];
        foreach (['type', 'alias', 'pattern', 'cast'] as $field) {
            yield $field . ' has an invalid type' => [[
                'types' => ['standard' => []],
                'operators' => ['=' => [$field => 123] + self::definition()],
            ]];
        }
        yield 'cast contains an invalid rule' => [[
            'types' => ['standard' => []],
            'operators' => ['=' => ['cast' => ['like', false]] + self::definition()],
        ]];
    }

    /**
     * @param array<array-key,mixed> $config
     */
    #[DataProvider('invalidStructures')]
    public function testLoaderRejectsInvalidStructures(array $config): void
    {
        $this->expectException(TranslatableInvalidArgumentException::class);
        (new OperatorLoader())->loadFromArray($config);
    }

    public function testLoaderRejectsScalarYamlRoot(): void
    {
        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('configuration');
        (new OperatorLoader())->loadFromFile(__DIR__ . '/../../../fixtures/functional/operators-scalar.yaml');
    }

    /**
     * @return iterable<string,array{string,string,mixed}>
     */
    public static function invalidGetterValues(): iterable
    {
        foreach (['type' => 'getType', 'name' => 'getName', 'description' => 'getDescription'] as $field => $method) {
            yield $field . ' is null' => [$field, $method, null];
            yield $field . ' is an integer' => [$field, $method, 123];
        }
        yield 'pattern is an array' => ['pattern', 'getValidationPattern', []];
        yield 'cast is an object' => ['cast', 'getCastingRules', new stdClass()];
        yield 'cast contains a null' => ['cast', 'getCastingRules', [null]];
    }

    #[DataProvider('invalidGetterValues')]
    public function testGettersValidateTheirReturnTypes(string $field, string $method, mixed $value): void
    {
        $operator = new Operator('=', [$field => $value] + self::definition());
        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage($field);
        $operator->$method();
    }

    public function testManagerRejectsInvalidAlias(): void
    {
        $operator = new Operator('=', ['alias' => []] + self::definition());
        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('alias');
        (new OperatorManager())->registerOperator($operator);
    }

    public function testLoaderRejectsAnAliasWhoseBaseHasNotBeenLoaded(): void
    {
        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('requires unloaded operator');
        (new OperatorLoader())->loadFromArray([
            'types' => ['standard' => []],
            'operators' => ['=' => ['alias' => 'missing'] + self::definition()],
        ]);
    }

    public function testValidationRejectsAMismatchedSymbol(): void
    {
        $this->expectException(TranslatableRuntimeException::class);
        $this->expectExceptionMessage('Symbol mismatch');
        (new OperatorLoader())->validate(['!=' => new Operator('=', self::definition())]);
    }

    /**
     * @return iterable<string,array{string,mixed}>
     */
    public static function invalidRequiredFields(): iterable
    {
        foreach (['type', 'name', 'description'] as $field) {
            yield $field . ' is missing' => [$field, null];
            yield $field . ' has the wrong type' => [$field, 123];
        }
    }

    #[DataProvider('invalidRequiredFields')]
    public function testValidationRejectsInvalidRequiredFields(string $field, mixed $value): void
    {
        $operator = new Operator('=', [$field => $value] + self::definition());
        $this->expectException(TranslatableRuntimeException::class);
        $this->expectExceptionMessage($field);
        (new OperatorLoader())->validate(['=' => $operator]);
    }

    public function testOptionalNullsAndAdditionalFieldsArePreserved(): void
    {
        $config = ['alias' => null, 'pattern' => null, 'cast' => null, 'extension' => ['custom' => true]] + self::definition();
        $operators = (new OperatorLoader())->loadFromArray([
            'types' => ['standard' => []],
            'operators' => ['=' => $config],
        ]);
        $operator = $operators['='];
        $this->assertSame('Equals', $operator->getName());
        $this->assertSame('Equality', $operator->getDescription());
        $this->assertNull($operator->getValidationPattern());
        $this->assertSame([], $operator->getCastingRules());
        $this->assertSame(['custom' => true], $operator->get('extension'));
        $this->assertSame('default', $operator->get('absent', 'default'));
        $this->assertSame('/.+/', (new Operator('custom:', $config))->getValidationPattern());
    }

    public function testKeyedCastingRulesAndExplicitPatternArePreserved(): void
    {
        $operator = new Operator('custom:', [
            'pattern' => '/^[0-9]+$/',
            'cast' => ['first' => 'like', 7 => 'list'],
        ] + self::definition());
        $this->assertSame('/^[0-9]+$/', $operator->getValidationPattern());
        $this->assertSame(['first' => 'like', 7 => 'list'], $operator->getCastingRules());
    }
}
