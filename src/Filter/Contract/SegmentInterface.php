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
 * Represents a single segment in a path with its metadata.
 */
interface SegmentInterface
{
    /**
     * Gets the segment's base name without metadata.
     *
     * @return string The segment name.
     */
    public function getName(): string;

    /**
     * Gets the join type if specified.
     *
     * @return string|null The join type or null if not specified.
     */
    public function getJoinType(): ?string;

    /**
     * Gets the alias if specified.
     *
     * @return string|null The alias or null if not specified.
     */
    public function getAlias(): ?string;

    /**
     * Gets the segment options if specified.
     *
     * @return array<string,string>|null The options or null if not specified.
     */
    public function getOptions(): ?array;
}
