<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Filter;

use Derafu\Query\Filter\Contract\CompositeConditionInterface;
use Derafu\Query\Filter\Contract\ConditionInterface;
use InvalidArgumentException;

/**
 * Implementation of composite query conditions.
 *
 * This class allows building complex query conditions by combining multiple
 * simple condition and other composite conditions with AND/OR operators.
 */
final class CompositeCondition implements CompositeConditionInterface
{
    /**
     * Valid composition types.
     *
     * @var array<string>
     */
    private const VALID_TYPES = ['AND', 'OR'];

    /**
     * List of conditions in this composite.
     *
     * @var array<int,ConditionInterface|CompositeConditionInterface>
     */
    private array $conditions = [];

    /**
     * Creates a new composite condition.
     *
     * @param string $type The composition type ('AND' or 'OR').
     * @throws InvalidArgumentException If type is invalid.
     */
    public function __construct(
        private readonly string $type
    ) {
        if (!in_array($type, self::VALID_TYPES)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid composite type: "%s". Must be one of: %s.',
                    $type,
                    implode(', ', self::VALID_TYPES)
                )
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * {@inheritDoc}
     */
    public function add(ConditionInterface|CompositeConditionInterface $condition): self
    {
        $this->conditions[] = $condition;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getConditions(): array
    {
        return $this->conditions;
    }

    /**
     * Creates a new AND composite.
     *
     * @return self The new composite.
     */
    public static function and(): self
    {
        return new self('AND');
    }

    /**
     * Creates a new OR composite.
     *
     * @return self The new composite.
     */
    public static function or(): self
    {
        return new self('OR');
    }
}
