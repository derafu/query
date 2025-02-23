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
    public function getColumn(): string
    {
        $segments = $this->segments;
        $lastSegment = end($segments);

        return $lastSegment->getName();
    }

    /**
     * {@inheritDoc}
     */
    public function getRelations(): array
    {
        return array_slice($this->segments, 0, -1);
    }

    /**
     * {@inheritDoc}
     */
    public function getSegments(): array
    {
        return $this->segments;
    }
}
