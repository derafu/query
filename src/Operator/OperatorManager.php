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
                sprintf('Operator already registered: %s.', $symbol)
            );
        }

        // Check if this operator uses another operator.
        $use = $operator->get('alias');
        if ($use !== null && !isset($this->operators[$use])) {
            throw new InvalidArgumentException(
                sprintf(
                    'Operator %s requires unregistered operator: %s.',
                    $symbol,
                    $use
                )
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
                sprintf('Operator not found: %s', $symbol)
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
