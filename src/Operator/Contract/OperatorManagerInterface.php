<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Operator\Contract;

use InvalidArgumentException;

/**
 * Registry and lifecycle manager for operators.
 *
 * This component manages the complete set of available operators in the system.
 * It handles operator registration, retrieval, and ensures proper use of
 * operator instances.
 */
interface OperatorManagerInterface
{
    /**
     * Registers a new operator.
     *
     * Adds a new operator type to the system.
     *
     * @param OperatorInterface $operator The operator.
     * @return self For method chaining.
     * @throws InvalidArgumentException If symbol already registered.
     */
    public function registerOperator(OperatorInterface $operator): self;

    /**
     * Retrieves an operator by its symbol.
     *
     * @param string $symbol The operator's unique symbol.
     * @return OperatorInterface The operator instance.
     * @throws InvalidArgumentException If no operator matches the symbol.
     */
    public function getOperator(string $symbol): OperatorInterface;

    /**
     * Gets all registered operators.
     *
     * Returns a map of all available operators in the system, keyed by their
     * symbols. Used for introspection and validation of query strings.
     *
     * @return array<string,OperatorInterface> Map of operator symbols to
     * instances.
     */
    public function getOperators(): array;

    /**
     * Gets operator symbols sorted by length (longest first).
     *
     * This ensures longer operators (like '!=') are matched before shorter ones
     * (like '!') when parsing.
     *
     * @return array<string> Sorted operator symbols.
     */
    public function getOperatorsSortedByLength(): array;
}
