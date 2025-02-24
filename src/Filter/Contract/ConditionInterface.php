<?php

declare(strict_types=1);

/**
 * Derafu: Query - Smart Query Builder.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
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
}
