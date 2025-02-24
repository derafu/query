<?php

declare(strict_types=1);

/**
 * Derafu: Query - Smart Query Builder.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Filter;

use Derafu\Query\Filter\Contract\ConditionInterface;
use Derafu\Query\Filter\Contract\FilterInterface;
use Derafu\Query\Filter\Contract\PathInterface;

/**
 * Implementation of simple query conditions.
 */
class Condition implements ConditionInterface
{
    public function __construct(
        private readonly PathInterface $path,
        private readonly FilterInterface $filter
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function getPath(): PathInterface
    {
        return $this->path;
    }

    /**
     * {@inheritDoc}
     */
    public function getFilter(): FilterInterface
    {
        return $this->filter;
    }
}
