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

use Derafu\Query\Filter\Contract\SegmentInterface;
use InvalidArgumentException;

/**
 * Immutable value object representing a path segment with metadata.
 */
final class Segment implements SegmentInterface
{
    /**
     * Creates a new segment instance.
     *
     * @param string $name The segment name.
     * @param string|null $joinType The join type if specified.
     * @param string|null $alias The segment alias if specified.
     * @param array<string,string>|null $options Additional options if specified.
     */
    public function __construct(
        private readonly string $name,
        private readonly ?string $joinType = null,
        private readonly ?string $alias = null,
        private readonly ?array $options = null
    ) {
        if (empty($name)) {
            throw new InvalidArgumentException('Segment name cannot be empty.');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * {@inheritDoc}
     */
    public function getJoinType(): ?string
    {
        return $this->joinType;
    }

    /**
     * {@inheritDoc}
     */
    public function getAlias(): ?string
    {
        return $this->alias;
    }

    /**
     * {@inheritDoc}
     */
    public function getOptions(): ?array
    {
        return $this->options;
    }
}
