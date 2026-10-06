<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsQuery\Functional;

use Closure;
use Derafu\Query\Bridge\Exception\UnsupportedOperatorException;
use Derafu\Query\Builder\Sql\SqlQuery;
use Derafu\Query\Config\JsonConfigLoader;
use Derafu\Query\Config\QueryConfig;
use Derafu\Query\Config\YamlConfigLoader;
use Derafu\Query\Filter\CompositeCondition;
use Derafu\Query\Filter\Filter;
use Derafu\Query\Filter\PathParser;
use Derafu\Query\Operator\Contract\OperatorInterface;
use Derafu\Query\Operator\OperatorLoader;
use Derafu\Query\Operator\OperatorManager;
use Derafu\Translation\Contract\TranslatableInterface;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * The errors of the package are translatable and say what they said before,
 * with the values that caused them in the message.
 */
#[CoversClass(QueryConfig::class)]
#[CoversClass(JsonConfigLoader::class)]
#[CoversClass(YamlConfigLoader::class)]
#[CoversClass(PathParser::class)]
#[CoversClass(Filter::class)]
#[CoversClass(CompositeCondition::class)]
#[CoversClass(SqlQuery::class)]
#[CoversClass(OperatorManager::class)]
#[CoversClass(OperatorLoader::class)]
#[CoversClass(UnsupportedOperatorException::class)]
final class ExceptionsTest extends TestCase
{
    /**
     * @return array<string, array{Closure(self): mixed, class-string<Throwable>, string}>
     */
    public static function errorsProvider(): array
    {
        return [
            'empty configuration' => [
                fn () => new QueryConfig([]),
                InvalidArgumentException::class,
                'Query configuration cannot be empty.',
            ],
            'unsupported extension' => [
                fn () => QueryConfig::fromFile('config.txt'),
                InvalidArgumentException::class,
                'Unsupported file extension: txt',
            ],
            'unreadable yaml file' => [
                fn () => (new YamlConfigLoader())->loadFromFile('/path/to/nonexistent.yaml'),
                RuntimeException::class,
                'Cannot read configuration file: /path/to/nonexistent.yaml',
            ],
            'unreadable json file' => [
                fn () => (new JsonConfigLoader())->loadFromFile('/path/to/nonexistent.json'),
                RuntimeException::class,
                'Cannot read configuration file: /path/to/nonexistent.json',
            ],
            'yaml that is not a configuration' => [
                fn () => (new YamlConfigLoader())->loadFromString('plain'),
                InvalidArgumentException::class,
                'Invalid YAML configuration format.',
            ],
            'json that is not a configuration' => [
                fn () => (new JsonConfigLoader())->loadFromString('"plain"'),
                InvalidArgumentException::class,
                'Invalid JSON configuration format.',
            ],
            'json that does not parse' => [
                fn () => (new JsonConfigLoader())->loadFromString('{'),
                InvalidArgumentException::class,
                'Error parsing JSON: Syntax error',
            ],
            'empty path' => [
                fn () => (new PathParser())->parse(''),
                InvalidArgumentException::class,
                'Path expression cannot be empty.',
            ],
            'unknown composite type' => [
                fn () => new CompositeCondition('XOR'),
                InvalidArgumentException::class,
                'Invalid composite type: "XOR". Must be one of: AND, OR.',
            ],
            'missing key of a query' => [
                fn () => (new SqlQuery('SELECT 1', []))[1234567],
                InvalidArgumentException::class,
                'Key 1234567 does not exists.',
            ],
            'query that is modified' => [
                function () {
                    $query = new SqlQuery('SELECT 1', []);
                    $query['sql'] = 'SELECT 2';
                },
                LogicException::class,
                'SQL Query data is immutable.',
            ],
            'operator that is not found' => [
                fn () => (new OperatorManager())->getOperator('non-existent'),
                InvalidArgumentException::class,
                'Operator not found: non-existent',
            ],
            'file of operators that is not found' => [
                fn () => (new OperatorLoader())->loadFromFile('/path/to/nonexistent.yaml'),
                InvalidArgumentException::class,
                'Configuration file not found: /path/to/nonexistent.yaml',
            ],
            'operators without sections' => [
                fn () => (new OperatorLoader())->loadFromArray(['some' => 'config']),
                RuntimeException::class,
                'Configuration must contain "types" and "operators" sections.',
            ],
            'operator that is not supported' => [
                fn () => throw UnsupportedOperatorException::forOperator('DATE'),
                RuntimeException::class,
                'Operator "DATE" is not supported in DQL context.',
            ],
            'operator that is not supported, with a reason' => [
                fn () => throw UnsupportedOperatorException::forOperator('DATE', 'no date functions'),
                RuntimeException::class,
                'Operator "DATE" is not supported in DQL context: no date functions.',
            ],
        ];
    }

    /**
     * @param Closure(): mixed $error
     * @param class-string<Throwable> $class
     */
    #[DataProvider('errorsProvider')]
    public function testEveryErrorIsTranslatableAndSaysTheSame(Closure $error, string $class, string $message): void
    {
        $exception = null;
        try {
            $error();
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf($class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertSame($message, $exception->getMessage());
    }

    public function testAValueThatIsNotValidForAnOperatorIsReported(): void
    {
        $operator = $this->createStub(OperatorInterface::class);
        $operator->method('getValidationPattern')->willReturn('/^[a-z]+$/');
        $operator->method('getSymbol')->willReturn('=');

        $exception = null;
        try {
            (new Filter($operator, '12'))->validate();
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertSame('Value "12" is not valid for operator "=".', $exception->getMessage());
    }

    public function testAnOperatorThatIsRegisteredTwiceIsReported(): void
    {
        $operator = $this->createStub(OperatorInterface::class);
        $operator->method('getSymbol')->willReturn('=');
        $operator->method('get')->willReturn(null);

        $manager = new OperatorManager();
        $manager->registerOperator($operator);

        $exception = null;
        try {
            $manager->registerOperator($operator);
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertSame('Operator already registered: =.', $exception->getMessage());
    }
}
