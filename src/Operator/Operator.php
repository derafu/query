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
use InvalidArgumentException;

/**
 * Represents a query operator.
 *
 * This class implements the core operator functionality, handling value
 * validation, pattern matching, and SQL generation according to its
 * configuration.
 */
final class Operator implements OperatorInterface
{
    /**
     * Current database engine for SQL generation.
     */
    private string $engine = 'default';

    /**
     * Creates a new operator instance.
     *
     * @param OperatorConfigInterface $config The operator's configuration.
     * @param OperatorInterface|null $baseOperator Base operator if this extends
     * another.
     */
    public function __construct(
        private readonly OperatorConfigInterface $config,
        private readonly ?OperatorInterface $baseOperator = null
    ) {
    }

    /**
     * Gets the operator's symbol.
     *
     * @return string The operator symbol.
     */
    public function getSymbol(): string
    {
        return $this->config->getSymbol();
    }

    /**
     * Gets the operator's configuration.
     *
     * @return OperatorConfigInterface The operator configuration.
     */
    public function getConfig(): OperatorConfigInterface
    {
        return $this->config;
    }

    /**
     * Gets the current database engine.
     *
     * @return string The database engine identifier.
     */
    public function getEngine(): string
    {
        return $this->engine;
    }

    /**
     * Sets the database engine for SQL generation.
     *
     * @param string $engine The database engine identifier.
     * @return self For method chaining.
     */
    public function setEngine(string $engine): self
    {
        $this->engine = $engine;
        if ($this->baseOperator) {
            $this->baseOperator->setEngine($engine);
        }

        return $this;
    }

    /**
     * Applies the operator to create a SQL condition.
     *
     * @param string $column The database column name.
     * @param string|null $value The value to apply the operator with.
     * @return array{sql: string, parameters: array<string,mixed>} The SQL and
     * parameters.
     */
    public function apply(string $column, ?string $value = null): array
    {
        // Validate value if pattern exists.
        $pattern = $this->config->getValidationPattern();
        if ($pattern !== null && $value !== null) {
            if (!preg_match($pattern, $value)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Invalid value format for operator %s: %s.',
                        $this->getSymbol(),
                        $value
                    )
                );
            }
        }

        // Apply casting rules.
        $value = $this->castValue($value);

        // If this operator uses another, delegate to it.
        if ($this->baseOperator) {
            return $this->baseOperator->apply($column, $value);
        }

        // Get SQL template for current engine.
        $templates = $this->config->getSqlTemplates();
        $sql = $templates[$this->engine] ?? $templates['default'] ?? '';

        if (empty($sql)) {
            throw new InvalidArgumentException(
                sprintf(
                    'No SQL template for operator %s on engine %s.',
                    $this->getSymbol(),
                    $this->engine
                )
            );
        }

        // Create unique parameter name.
        $param = 'param_' . uniqid();

        // Replace placeholders.
        $sql = strtr($sql, [
            '{{column}}' => $column,
            '{{value}}' => ':' . $param,
        ]);

        return [
            'sql' => $sql,
            'parameters' => [$param => $value],
        ];
    }

    /**
     * Applies casting rules to a value.
     *
     * @param string|null $value The value to cast.
     * @return string|null The casted value.
     */
    private function castValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $rules = $this->config->getValueCasting();
        foreach ($rules as $rule) {
            switch ($rule) {
                case 'like_start':
                    $value .= '%';
                    break;
                case 'like':
                    $value = '%' . $value . '%';
                    break;
                case 'like_end':
                    $value = '%' . $value;
                    break;
            }
        }

        return $value;
    }
}
