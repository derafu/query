<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Filter\Contract;

/**
 * Interface for simple query conditions.
 */
interface ConditionInterface
{
    /**
     * Gets the path of the condition.
     *
     * @return PathInterface
     */
    public function getPath(): PathInterface;

    /**
     * Gets the filter of the condition.
     *
     * @return FilterInterface
     */
    public function getFilter(): FilterInterface;

    /**
     * Indicates whether the filter value is for a literal condition or is a
     * value representing an identifier or expression.
     *
     * @return bool
     */
    public function isLiteral(): bool;
}
