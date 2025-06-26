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

use Stringable;

/**
 * Represents a parsed relation path with its segments and metadata.
 */
interface PathInterface extends Stringable
{
    /**
     * Gets all segments of the path.
     *
     * @return SegmentInterface[] All path segments.
     */
    public function getSegments(): array;

    /**
     * Gets the first segment of the path.
     *
     * @return SegmentInterface The first segment.
     */
    public function getFirstSegment(): SegmentInterface;

    /**
     * Gets the last segment of the path.
     *
     * @return SegmentInterface The last segment.
     */
    public function getLastSegment(): SegmentInterface;
}
