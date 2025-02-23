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
use Derafu\Query\Filter\Contract\PathParserInterface;
use InvalidArgumentException;

/**
 * Parser for field lookup expressions in the style of Django's lookups.
 *
 * Handles expressions like:
 *
 *   - profile__address__city (accessing nested model fields).
 *   - author+left__books (specifying join types for relations).
 *   - author(join:left)__books (alternative join syntax).
 *   - author:a__books (specifying aliases).
 *   - author(alias:a)__books (alternative alias syntax).
 *   - author(join:left,alias:a)__books (combined options).
 *
 * Each segment in the path represents a field/column name, not a table name.
 * The actual table names are determined by the model/entity configuration.
 */
final class PathParser implements PathParserInterface
{
    /**
     * Valid join types for related fields.
     *
     * @var array<string>
     */
    private const VALID_JOIN_TYPES = ['inner', 'left', 'cross', 'right'];

    /**
     * Pattern for valid field/column names.
     * Allows letters, numbers, and underscores, must start with a letter.
     */
    private const FIELD_NAME_PATTERN = '/^[a-zA-Z][a-zA-Z0-9_]*$/';

    public function parse(string $expression): PathInterface
    {
        if (empty($expression)) {
            throw new InvalidArgumentException(
                'Path expression cannot be empty.'
            );
        }

        $parts = explode('__', $expression);
        $segments = [];

        foreach ($parts as $part) {
            $segments[] = $this->parseSegment($part);
        }

        return new Path($segments);
    }

    /**
     * Parses a single segment of the path.
     *
     * Each segment represents a field/column name with optional metadata.
     *
     * @param string $expression The segment expression to parse.
     * @return Segment The parsed segment.
     * @throws InvalidArgumentException If segment format is invalid.
     */
    private function parseSegment(string $expression): Segment
    {
        $name = $expression;
        $joinType = null;
        $alias = null;
        $options = null;

        // First validate the initial raw name is not empty.
        if (empty($name)) {
            throw new InvalidArgumentException('Field name cannot be empty.');
        }

        // Parse options first as they are the most distinctive.
        if (preg_match('/^(.+?)\(([\w:,]+)\)$/', $name, $matches)) {
            $name = $matches[1];
            $options = $this->parseOptions($matches[2]);

            // Check if options contain join or alias.
            if (isset($options['join'])) {
                $joinType = strtolower($options['join']);
                if (!in_array($joinType, self::VALID_JOIN_TYPES)) {
                    throw new InvalidArgumentException(
                        sprintf('Invalid join type: %s.', $joinType)
                    );
                }
            }
            if (isset($options['alias'])) {
                $alias = $options['alias'];
            }
        }

        // Support simple operator syntax if options not present.
        if ($options === null) {
            // Check for join type (+left, +inner, +cross, +right).
            if (preg_match('/^(.+)\+(\w+)$/', $name, $matches)) {
                $name = $matches[1];
                $joinType = strtolower($matches[2]);
                if (!in_array($joinType, self::VALID_JOIN_TYPES)) {
                    throw new InvalidArgumentException(
                        sprintf('Invalid join type: %s.', $joinType)
                    );
                }
            }

            // Check for alias (:alias).
            if (preg_match('/^(.+):(\w+)$/', $name, $matches)) {
                $name = $matches[1];
                $alias = $matches[2];
            }
        }

        // Validate the field name after removing all metadata.
        if (!preg_match(self::FIELD_NAME_PATTERN, $name)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid field name: %s. Field names must start with a letter and contain only letters, numbers, and underscores.',
                    $name
                )
            );
        }

        return new Segment(
            name: $name,
            joinType: $joinType,
            alias: $alias,
            options: $options
        );
    }

    /**
     * Parses the options string into an associative array.
     *
     * @param string $options The options string (e.g., "order:created_at,limit:10").
     * @return array<string,string> The parsed options.
     * @throws InvalidArgumentException If options format is invalid.
     */
    private function parseOptions(string $options): array
    {
        $result = [];
        $pairs = explode(',', $options);

        foreach ($pairs as $pair) {
            if (!str_contains($pair, ':')) {
                throw new InvalidArgumentException(
                    sprintf('Invalid option format: %s.', $pair)
                );
            }

            [$key, $value] = explode(':', $pair, 2);
            $key = trim($key);
            $value = trim($value);

            if (empty($key) || empty($value)) {
                throw new InvalidArgumentException(
                    'Option key and value cannot be empty.'
                );
            }

            $result[$key] = $value;
        }

        return $result;
    }
}
