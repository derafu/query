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

use InvalidArgumentException;

/**
 * Parser for operator strings.
 *
 * This component handles the parsing of operator strings into their constituent
 * parts. It's responsible for identifying operators and extracting their values
 * from query strings, ensuring they match defined patterns and rules.
 */
interface FilterParserInterface
{
    /**
     * Extracts a value from an operator string.
     *
     * Takes a full operator expression (like "=123" or "~text") and extracts
     * just the value portion. The method must handle all valid operator
     * syntaxes defined in the system.
     *
     * @param string $expression The complete operator expression to parse.
     * @return FilterInterface Extracted filter.
     * @throws InvalidArgumentException If the expression format is invalid.
     */
    public function parse(string $expression): FilterInterface;
}
