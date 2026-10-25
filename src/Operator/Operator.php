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

use Derafu\Array\ArrayReader;
use Derafu\Query\Operator\Contract\OperatorInterface;
use Derafu\Value\ValueType;

/**
 * Represents a query operator.
 *
 * This class implements the core operator functionality, handling value
 * validation, pattern matching, and SQL generation according to its
 * configuration.
 */
final class Operator implements OperatorInterface
{
    private readonly ArrayReader $reader;

    /**
     * Creates a new operator instance.
     *
     * @param string $symbol The operator's symbol.
     * @param array<array-key,mixed> $config Configuration array for the operator.
     */
    public function __construct(
        private readonly string $symbol,
        private readonly array $config,
        private readonly ?OperatorInterface $baseOperator = null
    ) {
        $this->reader = new ArrayReader($config, $symbol);
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
        return $this->reader->require('type', ValueType::string());
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return $this->reader->require('name', ValueType::string());
    }

    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return $this->reader->require('description', ValueType::string());
    }

    /**
     * {@inheritDoc}
     */
    public function getValidationPattern(): ?string
    {
        if (($pattern = $this->reader->getString('pattern')) !== null) {
            return $pattern;
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
        $rules = $this->reader->getArray('cast') ?? [];
        return ValueType::arrayOf(ValueType::string())->parse($rules, 'cast', $this->symbol);
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
