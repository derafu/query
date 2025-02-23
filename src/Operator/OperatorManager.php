<?php

declare(strict_types=1);

/**
 * Derafu: Query - Smart Query Builder.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Operator;

use Derafu\Query\Operator\Contract\OperatorConfigInterface;
use Derafu\Query\Operator\Contract\OperatorInterface;
use Derafu\Query\Operator\Contract\OperatorManagerInterface;
use InvalidArgumentException;

/**
 * Manages the lifecycle of query operators.
 *
 * This class handles operator registration, creation, and provides access to
 * operators based on their symbols. It ensures operators are properly
 * initialized and dependencies between operators are correctly managed.
 */
final class OperatorManager implements OperatorManagerInterface
{
    /**
     * Map of registered operators by their symbols.
     *
     * @var array<string,OperatorInterface>
     */
    private array $operators = [];

    /**
     * Map of operator configurations by their symbols.
     *
     * @var array<string,OperatorConfigInterface>
     */
    private array $configs = [];

    /**
     * Construct a new manager.
     *
     * @param array<string,OperatorConfigInterface> $operators
     */
    public function __construct(array $operators = [])
    {
        foreach ($operators as $operator) {
            $this->registerOperator($operator);
        }
    }

    /**
     * Gets an operator instance by its symbol.
     *
     * @param string $symbol The operator's symbol.
     * @return OperatorInterface The operator instance.
     * @throws InvalidArgumentException If operator not found.
     */
    public function getOperator(string $symbol): OperatorInterface
    {
        if (!isset($this->operators[$symbol])) {
            if (!isset($this->configs[$symbol])) {
                throw new InvalidArgumentException(
                    sprintf('Operator not found: %s', $symbol)
                );
            }
            $this->operators[$symbol] = $this->createOperator(
                $this->configs[$symbol]
            );
        }

        return $this->operators[$symbol];
    }

    /**
     * Gets all registered operators.
     *
     * @return array<string,OperatorInterface> Map of operators by symbol.
     */
    public function getOperators(): array
    {
        // Ensure all operators are instantiated.
        foreach ($this->configs as $symbol => $config) {
            if (!isset($this->operators[$symbol])) {
                $this->operators[$symbol] = $this->createOperator($config);
            }
        }

        return $this->operators;
    }

    /**
     * Registers a new operator configuration.
     *
     * @param OperatorConfigInterface $config The operator configuration.
     * @return self For method chaining.
     * @throws InvalidArgumentException If operator already registered or
     * dependencies not met.
     */
    public function registerOperator(OperatorConfigInterface $config): self
    {
        $symbol = $config->getSymbol();

        // Check for duplicate registration.
        if (isset($this->configs[$symbol])) {
            throw new InvalidArgumentException(
                sprintf('Operator already registered: %s.', $symbol)
            );
        }

        // Check if this operator uses another operator.
        $use = $config->get('use');
        if ($use !== null && !isset($this->configs[$use])) {
            throw new InvalidArgumentException(
                sprintf(
                    'Operator %s requires unregistered operator: %s.',
                    $symbol,
                    $use
                )
            );
        }

        // Store configuration.
        $this->configs[$symbol] = $config;

        // Clear cached operator instance if it exists.
        unset($this->operators[$symbol]);

        return $this;
    }

    /**
     * Creates a new operator instance from its configuration.
     *
     * @param OperatorConfigInterface $config The operator configuration.
     * @return OperatorInterface The created operator.
     */
    private function createOperator(
        OperatorConfigInterface $config
    ): OperatorInterface {
        $use = $config->get('use');
        $baseOperator = $use ? $this->getOperator($use) : null;

        return new Operator(
            config: $config,
            baseOperator: $baseOperator
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getOperatorsSortedByLength(): array
    {
        $operators = array_keys($this->configs);
        usort($operators, fn ($a, $b) => strlen($b) - strlen($a));

        return $operators;
    }
}
