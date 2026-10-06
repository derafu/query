<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Operator;

use Derafu\Query\Operator\Contract\OperatorInterface;
use Derafu\Query\Operator\Contract\OperatorManagerInterface;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;

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
     * Construct a new manager.
     *
     * @param array<string,OperatorInterface> $operators
     */
    public function __construct(array $operators = [])
    {
        foreach ($operators as $operator) {
            $this->registerOperator($operator);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function registerOperator(OperatorInterface $operator): self
    {
        $symbol = $operator->getSymbol();

        // Check for duplicate registration.
        if (isset($this->operators[$symbol])) {
            throw new InvalidArgumentException(
                ['Operator already registered: {symbol}.', 'symbol' => (string) $symbol]
            );
        }

        // Check if this operator uses another operator.
        $use = $operator->get('alias');
        if ($use !== null && !isset($this->operators[$use])) {
            throw new InvalidArgumentException(
                [
                    'Operator {symbol} requires unregistered operator: {required}.',
                    'symbol' => (string) $symbol,
                    'required' => (string) $use,
                ]
            );
        }

        // Store operator.
        $this->operators[$symbol] = $operator;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getOperator(string $symbol): OperatorInterface
    {
        if (!isset($this->operators[$symbol])) {
            throw new InvalidArgumentException(
                ['Operator not found: {symbol}', 'symbol' => (string) $symbol]
            );
        }

        return $this->operators[$symbol];
    }

    /**
     * {@inheritDoc}
     */
    public function getOperators(): array
    {
        return $this->operators;
    }

    /**
     * {@inheritDoc}
     */
    public function getOperatorsSortedByLength(): array
    {
        $operators = array_keys($this->operators);
        usort($operators, fn ($a, $b) => strlen($b) - strlen($a));

        return $operators;
    }
}
