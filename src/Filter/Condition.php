<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
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
        private readonly FilterInterface $filter,
        private readonly bool $literal = true
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

    /**
     * {@inheritDoc}
     */
    public function isLiteral(): bool
    {
        return $this->literal;
    }
}
