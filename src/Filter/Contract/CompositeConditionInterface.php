<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Filter\Contract;

/**
 * Interface for composite query conditions.
 *
 * Defines how composite conditions should be structured. Composites can contain
 * both regular filters and other composites, allowing for nested conditions
 * connected by AND/OR operators.
 */
interface CompositeConditionInterface
{
    /**
     * Gets the type of composition (AND/OR).
     *
     * @return string The composition type ('AND' or 'OR').
     */
    public function getType(): string;

    /**
     * Adds a condition (simple or composite) to this composite.
     *
     * @param ConditionInterface|CompositeConditionInterface $condition The condition to add.
     * @return self For method chaining.
     */
    public function add(ConditionInterface|CompositeConditionInterface $condition): self;

    /**
     * Gets all conditions in this composite.
     *
     * @return array<int,ConditionInterface|CompositeConditionInterface> The conditions.
     */
    public function getConditions(): array;
}
