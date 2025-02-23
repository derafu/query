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
     * Creates a new operator instance.
     *
     * @param string $symbol The operator's symbol.
     * @param array $config Configuration array for the operator.
     */
    public function __construct(
        private readonly string $symbol,
        private readonly array $config,
        private readonly ?OperatorInterface $baseOperator = null
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function getSymbol(): string
    {
        return $this->symbol;
    }

    /**
     * {@inheritDoc}
     */
    public function getType(): string
    {
        return $this->config['type'];
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return $this->config['name'];
    }

    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return $this->config['description'];
    }

    /**
     * {@inheritDoc}
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
     * {@inheritDoc}
     */
    public function getCastingRules(): array
    {
        return $this->config['cast'] ?? [];
    }

    /**
     * {@inheritDoc}
     */
    public function getBaseOperator(): ?OperatorInterface
    {
        return $this->baseOperator;
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $name, mixed $default = null): mixed
    {
        return $this->config[$name] ?? $default;
    }
}
