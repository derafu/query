<?php

declare(strict_types=1);

/**
 * Derafu: Query - Smart Query Builder.
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
 * It handles operator registration, retrieval, and ensures proper configuration
 * of operator instances.
 */
interface OperatorManagerInterface
{
    /**
     * Retrieves an operator by its symbol.
     *
     * Looks up and instantiates an operator based on its unique symbol. The
     * returned operator will be fully configured and ready to use.
     *
     * @param string $symbol The operator's unique symbol.
     * @return OperatorInterface The configured operator instance.
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
     * Registers a new operator configuration.
     *
     * Adds a new operator type to the system. The configuration defines all
     * aspects of the operator's behavior and will be used to instantiate
     * operator instances as needed.
     *
     * @param OperatorConfigInterface $config The operator configuration.
     * @return self For method chaining.
     * @throws InvalidArgumentException If symbol already registered.
     */
    public function registerOperator(OperatorConfigInterface $config): self;

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
