<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Filter\Contract;

use Stringable;

/**
 * Represents a single segment in a path with its metadata.
 */
interface SegmentInterface extends Stringable
{
    /**
     * Gets the segment's base name without metadata.
     *
     * @return string The segment name.
     */
    public function getName(): string;

    /**
     * Gets the segment options if specified.
     *
     * @return array<string,string|array<string,string>>
     */
    public function getOptions(): array;

    /**
     * Gets an option from options list of the segment.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getOption(string $key, mixed $default = null): mixed;
}
