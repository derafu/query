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
use Derafu\Query\Filter\Contract\PathParserInterface;
use InvalidArgumentException;

/**
 * Parser for field lookup expressions.
 *
 * Handles expressions like:
 *
 *   - profile__address__city (accessing nested fields).
 *   - author[alias:a]__books (specifying alias option).
 *   - invoices__customers[on:customer_id=id]__name (specifying relation with equals).
 *   - author[alias:a,on:author_id=id]__books (multiple options).
 *
 * Each segment in the path represents a field/column name with optional metadata.
 */
final class PathParser implements PathParserInterface
{
    /**
     * {@inheritDoc}
     */
    public function parse(string $expression): PathInterface
    {
        if (empty($expression)) {
            throw new InvalidArgumentException(
                'Path expression cannot be empty.'
            );
        }

        if (str_starts_with($expression, '___')) {
            return $this->parseExistsPath(substr($expression, 3));
        }

        $parts = explode('__', $expression);
        $segments = [];

        foreach ($parts as $part) {
            $segments[] = $this->parseSegment($part);
        }

        return new Path($segments);
    }

    /**
     * Parses an exists-subquery path (one that started with ___).
     *
     * The body is split on ___ to obtain nesting levels. Within each level
     * segments are separated by __. The first segment of every level receives
     * a synthetic 'subquery' => 'exists' option so that SQL/DQL builders know
     * to open a correlated EXISTS clause at that point.
     *
     * @throws InvalidArgumentException If the body is empty or any segment is invalid.
     */
    private function parseExistsPath(string $body): PathInterface
    {
        if (empty($body)) {
            throw new InvalidArgumentException(
                'Exists path body cannot be empty after ___.'
            );
        }

        $levels = explode('___', $body);
        $segments = [];

        foreach ($levels as $level) {
            $parts = explode('__', $level);

            foreach ($parts as $index => $part) {
                $synthetic = ($index === 0) ? ['subquery' => 'exists'] : [];
                $segments[] = $this->parseSegment($part, $synthetic);
            }
        }

        return new Path($segments);
    }

    /**
     * Parses a single segment of the path.
     *
     * Each segment represents a field/column name with optional metadata.
     *
     * @param string $expression The segment expression to parse.
     * @param array<string,mixed> $syntheticOptions Options injected by the parser
     *        (appended after user-defined options).
     * @return Segment The parsed segment.
     * @throws InvalidArgumentException If segment format is invalid.
     */
    private function parseSegment(string $expression, array $syntheticOptions = []): Segment
    {
        $name = $expression;
        $options = [];

        // First validate the initial raw name is not empty.
        if (empty($name)) {
            throw new InvalidArgumentException('Field name cannot be empty.');
        }

        // Parse options if present (using [key:value,key2:value2] syntax)
        if (preg_match('/^(.+?)\[([\w:,=]+)\]$/', $name, $matches)) {
            $name = $matches[1];
            $options = $this->parseOptions($matches[2]);
        }

        // Validate the field name after removing all metadata.
        if (empty($name)) {
            throw new InvalidArgumentException('Field name cannot be empty.');
        }

        // Basic check (not sanitization).
        if (preg_match('/^[^a-zA-Z0-9_]|[^a-zA-Z0-9_)_]$/', $name)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid characters found in segment name %s. Allowed at the beginning: letters, numbers, underscore. Allowed at the end: letters, numbers, underscore, closing parenthesis.',
                $name
            ));
        }

        return new Segment(
            name: $name,
            options: array_merge($options, $syntheticOptions)
        );
    }

    /**
     * Parses the options string into an associative array.
     *
     * @param string $options The options string (e.g., "key1:value1,key2:value2").
     * @return array<string,mixed> The parsed options.
     * @throws InvalidArgumentException If options format is invalid.
     */
    private function parseOptions(string $options): array
    {
        $result = [];
        $pairs = explode(',', $options);

        foreach ($pairs as $pair) {
            // Check if it contains a colon.
            if (!str_contains($pair, ':')) {
                throw new InvalidArgumentException(
                    sprintf('Invalid option format: %s.', $pair)
                );
            }

            // Split at first colon to get key and value.
            [$key, $value] = explode(':', $pair, 2);
            $key = trim($key);
            $value = trim($value);

            if (empty($key) || empty($value)) {
                throw new InvalidArgumentException(
                    'Option key and value cannot be empty.'
                );
            }

            // Check if the value contains an equals sign.
            if (str_contains($value, '=')) {
                [$leftPart, $rightPart] = explode('=', $value, 2);
                $leftPart = trim($leftPart);
                $rightPart = trim($rightPart);

                if (empty($leftPart) || empty($rightPart)) {
                    throw new InvalidArgumentException(
                        'Option parts around equals sign cannot be empty.'
                    );
                }

                // For options with equals, create a nested array if not exists.
                if (!isset($result[$key])) {
                    $result[$key] = [];
                }

                // Store as key-value pair within the option.
                $result[$key][$leftPart] = $rightPart;
            } else {
                // Simple option.
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
