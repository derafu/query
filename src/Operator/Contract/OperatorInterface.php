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
use RuntimeException;

/**
 * Core operator functionality.
 *
 * An operator represents a single type of database query condition. It knows
 * how to transform input values into SQL expressions according to its
 * configuration and the target database engine.
 */
interface OperatorInterface
{
    /**
     * Gets the operator's symbol.
     *
     * @return string The operator symbol.
     */
    public function getSymbol(): string;

    /**
     * Gets the operator's complete configuration.
     *
     * Provides access to all metadata and rules that define this operator's
     * behavior, including its symbol, type, and validation rules.
     *
     * @return OperatorConfigInterface The complete operator configuration.
     */
    public function getConfig(): OperatorConfigInterface;

    /**
     * Gets the current database engine setting.
     *
     * Returns the identifier of the database engine this operator is currently
     * configured to generate SQL for (e.g., 'mysql', 'postgresql').
     *
     * @return string The database engine identifier.
     */
    public function getEngine(): string;

    /**
     * Sets the target database engine.
     *
     * Configures which database engine's SQL dialect should be used when
     * generating query conditions. Must be one of the engines supported in the
     * operator's configuration.
     *
     * @param string $engine The database engine identifier.
     * @return self For method chaining.
     * @throws InvalidArgumentException If the engine is not supported.
     */
    public function setEngine(string $engine): self;

    /**
     * Generates a SQL condition from a column and value.
     *
     * Creates a complete SQL WHERE clause fragment using the operator's rules.
     * Returns both the SQL string with placeholders and the values to bind to
     * those placeholders.
     *
     * @param string $column The database column name to operate on.
     * @param string|null $value The value to use in the condition.
     * @return array{sql: string, parameters: array<string,mixed>} SQL and
     * parameters.
     * @throws InvalidArgumentException If the value is invalid.
     * @throws RuntimeException If SQL cannot be generated.
     */
    public function apply(string $column, ?string $value = null): array;
}
