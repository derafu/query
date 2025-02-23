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

/**
 * Configuration for a database query operator.
 *
 * This interface defines the structure for operator configurations. Each
 * operator needs metadata and rules that define its behavior when creating SQL
 * queries. The configuration determines how the operator will be identified,
 * its constraints, and how it transforms values into SQL expressions.
 */
interface OperatorConfigInterface
{
    /**
     * Gets the operator's symbol.
     *
     * The symbol is a unique identifier that represents this operator in query
     * strings. For example, '=' for equality or '~' for pattern matching. This
     * symbol must be unique among all registered operators in the system.
     *
     * @return string The operator's unique symbol.
     */
    public function getSymbol(): string;

    /**
     * Gets the operator's type.
     *
     * The type categorizes the operator's behavior. Common types include
     * 'comparison' for operators like '=' or '>', 'pattern' for LIKE-style
     * operators, and 'null' for NULL-checking operators.
     *
     * @return string The operator's behavioral type.
     */
    public function getType(): string;

    /**
     * Gets the operator's display name.
     *
     * A human-readable name for the operator that can be used in user
     * interfaces or documentation.
     *
     * @return string The operator's display name.
     */
    public function getName(): string;

    /**
     * Gets the operator's description.
     *
     * A detailed explanation of what the operator does and how it should be
     * used.
     *
     * @return string The operator's description.
     */
    public function getDescription(): string;

    /**
     * Gets the SQL template map for different database engines.
     *
     * Each database engine may require different SQL syntax for the same
     * logical operation. This method returns templates mapped by engine
     * identifier, like 'mysql' => 'COLUMN LIKE ?'.
     *
     * @return array<string,string> Map of engine identifiers to SQL templates.
     */
    public function getSqlTemplates(): array;

    /**
     * Gets the validation pattern for operator values.
     *
     * If defined, values used with this operator must match this regular
     * expression pattern. For example, a date operator might require values in
     * 'YYYY-MM-DD' format.
     *
     * @return string|null The regex pattern or null if no validation needed.
     */
    public function getValidationPattern(): ?string;

    /**
     * Gets the value casting rules.
     *
     * Defines how raw string values should be converted before using in SQL.
     * For example, ['type' => 'int'] would ensure numeric comparison, or
     * ['list' => ','] would split string into array.
     *
     * @return array<string,mixed> The casting configuration rules.
     */
    public function getValueCasting(): array;

    /**
     * Gets a named configuration value.
     *
     * @param string $name The configuration key.
     * @return mixed The configuration value.
     */
    public function get(string $name): mixed;
}
