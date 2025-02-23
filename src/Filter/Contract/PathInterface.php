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
 * Represents a parsed relation path with its segments and metadata.
 */
interface PathInterface
{
    /**
     * Gets the target column (last segment).
     *
     * @return string The name of the target column.
     */
    public function getColumn(): string;

    /**
     * Gets the relation segments (all segments except the last).
     *
     * @return array<SegmentInterface> The relation segments.
     */
    public function getRelations(): array;

    /**
     * Gets all segments including the target column.
     *
     * @return array<SegmentInterface> All path segments.
     */
    public function getSegments(): array;
}
