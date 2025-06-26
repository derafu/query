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

use Derafu\Query\Filter\Contract\PathInterface;
use Derafu\Query\Filter\Contract\SegmentInterface;
use InvalidArgumentException;

/**
 * Immutable value object representing a parsed relation path.
 */
final class Path implements PathInterface
{
    /**
     * Creates a new path instance.
     *
     * @param array<int,SegmentInterface> $segments The path segments.
     */
    public function __construct(
        private readonly array $segments
    ) {
        if (empty($segments)) {
            throw new InvalidArgumentException(
                'Path must have at least one segment.'
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getSegments(): array
    {
        return $this->segments;
    }

    /**
     * {@inheritDoc}
     */
    public function getFirstSegment(): SegmentInterface
    {
        return $this->segments[0];
    }

    /**
     * {@inheritDoc}
     */
    public function getLastSegment(): SegmentInterface
    {
        $segments = $this->segments;
        $lastSegment = end($segments);

        return $lastSegment;
    }

    /**
     * Convert path to its string representation.
     *
     * @return string
     */
    public function __toString(): string
    {
        if (empty($this->segments)) {
            return '';
        }

        $parts = [];

        foreach ($this->segments as $segment) {
            $parts[] = (string)$segment;
        }

        return implode('__', $parts);
    }
}
