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

/**
 * Represents immutable operator configuration.
 *
 * This class holds all configuration data for a specific operator, providing
 * type-safe access to configuration values.
 */
final class OperatorConfig implements OperatorConfigInterface
{
    /**
     * Creates a new operator configuration.
     *
     * @param string $symbol The operator's symbol.
     * @param array $config Configuration array for the operator.
     */
    public function __construct(
        private readonly string $symbol,
        private readonly array $config
    ) {
    }

    /**
     * Gets the operator's symbol.
     *
     * @return string The operator's unique symbol.
     */
    public function getSymbol(): string
    {
        return $this->symbol;
    }

    /**
     * Gets the operator's type.
     *
     * @return string The operator's behavioral type.
     */
    public function getType(): string
    {
        return $this->config['type'];
    }

    /**
     * Gets the operator's display name.
     *
     * @return string The operator's display name.
     */
    public function getName(): string
    {
        return $this->config['name'];
    }

    /**
     * Gets the operator's description.
     *
     * @return string The operator's description.
     */
    public function getDescription(): string
    {
        return $this->config['description'];
    }

    /**
     * Gets the SQL template map for different database engines.
     *
     * @return array<string,string> Map of engine identifiers to SQL templates.
     */
    public function getSqlTemplates(): array
    {
        if (isset($this->config['sql']) && is_array($this->config['sql'])) {
            return $this->config['sql'];
        }
        return ['default' => $this->config['sql'] ?? ''];
    }

    /**
     * Gets the validation pattern for operator values.
     *
     * @return string|null The regex pattern or null if no validation needed.
     */
    public function getValidationPattern(): ?string
    {
        if (isset($this->config['pattern'])) {
            return $this->config['pattern'];
        }

        if (str_ends_with($this->symbol, ':')) {
            return '/.+/';
        }

        return null;
    }

    /**
     * Gets the value casting rules.
     *
     * @return array<string,mixed> The casting configuration rules.
     */
    public function getValueCasting(): array
    {
        return $this->config['cast'] ?? [];
    }

    /**
     * Gets a named configuration value.
     *
     * @param string $name The configuration key.
     * @return mixed The configuration value.
     */
    public function get(string $name): mixed
    {
        return $this->config[$name] ?? null;
    }
}
