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
     * @param array<string,string|array<string,string>> $options
     */
    public function __construct(
        private readonly string $name,
        private readonly array $options = []
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
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * {@inheritDoc}
     */
    public function getOption(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    /**
     * {@inheritDoc}
     */
    public function __toString(): string
    {
        if ($this->options === []) {
            return $this->name;
        }

        $optionPairs = [];
        foreach ($this->options as $key => $value) {
            // Handle option arrays (for relation conditions like on:column=column).
            if (is_array($value)) {
                foreach ($value as $leftPart => $rightPart) {
                    $optionPairs[] = $key . ':' . $leftPart . '=' . $rightPart;
                }
            }
            // Handle simple values (like alias:value).
            else {
                $optionPairs[] = $key . ':' . $value;
            }
        }

        return $this->name . '[' . implode(',', $optionPairs) . ']';
    }
}
