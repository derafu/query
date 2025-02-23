<?php

declare(strict_types=1);

/**
 * Derafu: Query - Smart Query Builder.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsQuery\Operator;

use Derafu\Query\Operator\Contract\OperatorInterface;
use Derafu\Query\Operator\Contract\OperatorManagerInterface;
use Derafu\Query\Operator\Operator;
use Derafu\Query\Operator\OperatorManager;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the operator manager.
 *
 * Verifies that the operator manager can properly register, retrieve and handle
 * operators with their configurations.
 */
#[CoversClass(OperatorManager::class)]
#[CoversClass(Operator::class)]
final class OperatorManagerTest extends TestCase
{
    /**
     * Instance of the manager being tested.
     */
    private OperatorManagerInterface $manager;

    /**
     * Sets up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new OperatorManager();
    }

    /**
     * Tests basic operator registration and retrieval.
     */
    public function testBasicOperatorRegistration(): void
    {
        $config = $this->createOperatorConfig('=', [
            'type' => 'standard',
            'name' => 'Equals',
            'description' => 'Exact value match',
            'sql' => '{{column}} = {{value}}',
        ]);

        $this->manager->registerOperator($config);

        $operator = $this->manager->getOperator('=');
        $this->assertInstanceOf(OperatorInterface::class, $operator);
        $this->assertSame('=', $operator->getSymbol());
        $this->assertSame('standard', $operator->getType());
    }

    /**
     * Tests registering multiple operators.
     */
    public function testMultipleOperators(): void
    {
        $configs = [
            '=' => [
                'type' => 'standard',
                'name' => 'Equals',
                'description' => 'Exact value match',
                'sql' => '{{column}} = {{value}}',
            ],
            '!=' => [
                'type' => 'standard',
                'name' => 'Not Equals',
                'description' => 'Different value match',
                'sql' => '{{column}} != {{value}}',
            ],
        ];

        foreach ($configs as $symbol => $config) {
            $this->manager->registerOperator(
                $this->createOperatorConfig($symbol, $config)
            );
        }

        $operators = $this->manager->getOperators();
        $this->assertCount(2, $operators);
        $this->assertArrayHasKey('=', $operators);
        $this->assertArrayHasKey('!=', $operators);
    }

    /**
     * Tests operator that uses another operator.
     */
    public function testOperatorWithUse(): void
    {
        // Register the base operator first.
        $baseConfig = $this->createOperatorConfig('like:', [
            'type' => 'like',
            'name' => 'Like',
            'description' => 'Pattern match',
            'sql' => '{{column}} LIKE {{value}}',
        ]);
        $this->manager->registerOperator($baseConfig);

        // Register operator that uses base operator.
        $derivedConfig = $this->createOperatorConfig('^', [
            'type' => 'autolike',
            'name' => 'Starts With',
            'description' => 'Starts with pattern',
            'use' => 'like:',
            'cast' => ['like_start'],
        ]);
        $this->manager->registerOperator($derivedConfig);

        $operator = $this->manager->getOperator('^');
        $this->assertSame('^', $operator->getSymbol());
        $this->assertSame('autolike', $operator->getType());
    }

    /**
     * Tests retrieving a non-existent operator.
     */
    public function testGetNonExistentOperator(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->manager->getOperator('non-existent');
    }

    /**
     * Tests registering duplicate operator symbols.
     */
    public function testRegisterDuplicateOperator(): void
    {
        $config = $this->createOperatorConfig('=', [
            'type' => 'standard',
            'name' => 'Equals',
            'description' => 'Exact value match',
            'sql' => '{{column}} = {{value}}',
        ]);

        $this->manager->registerOperator($config);

        $this->expectException(InvalidArgumentException::class);
        $this->manager->registerOperator($config);
    }

    /**
     * Tests registering an operator that uses a non-existent operator.
     */
    public function testRegisterWithNonExistentUse(): void
    {
        $config = $this->createOperatorConfig('^', [
            'type' => 'autolike',
            'name' => 'Starts With',
            'description' => 'Starts with pattern',
            'use' => 'non-existent:',
            'cast' => ['like_start'],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->manager->registerOperator($config);
    }

    /**
     * Tests operator registration order when operators use other operators.
     */
    public function testOperatorRegistrationOrder(): void
    {
        // Register derived operator first (should fail)
        $derivedConfig = $this->createOperatorConfig('^', [
            'type' => 'autolike',
            'name' => 'Starts With',
            'description' => 'Starts with pattern',
            'use' => 'like:',
            'cast' => ['like_start'],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->manager->registerOperator($derivedConfig);
    }

    /**
     * Creates a mock operator config for testing.
     *
     * @param string $symbol The operator symbol.
     * @param array $config The operator configuration.
     * @return OperatorInterface The mock config.
     */
    private function createOperatorConfig(
        string $symbol,
        array $config
    ): OperatorInterface {
        $mockConfig = $this->createMock(OperatorInterface::class);
        $mockConfig->method('getSymbol')->willReturn($symbol);
        $mockConfig->method('getType')->willReturn($config['type']);
        $mockConfig->method('getName')->willReturn($config['name']);
        $mockConfig->method('getDescription')
            ->willReturn($config['description'])
        ;
        $mockConfig->method('getValidationPattern')
            ->willReturn($config['pattern'] ?? null)
        ;
        $mockConfig->method('getCastingRules')
            ->willReturn($config['cast'] ?? [])
        ;
        $mockConfig->method('get')->willReturnCallback(
            fn ($key) => $config[$key] ?? null
        );

        return $mockConfig;
    }
}
