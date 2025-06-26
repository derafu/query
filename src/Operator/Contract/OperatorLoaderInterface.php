<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Operator\Contract;

use InvalidArgumentException;
use RuntimeException;

/**
 * Loader for operators.
 *
 * This component handles loading operators from various sources and validating
 * them before use. It supports both file-based and programmatic configuration
 * loading.
 */
interface OperatorLoaderInterface
{
    /**
     * Loads operators from a file.
     *
     * Reads a configuration file and converts it into operators.
     *
     * @param string $path Path to the configuration file.
     * @return array<string,OperatorInterface> Map of symbols to operators.
     * @throws InvalidArgumentException If file cannot be read.
     * @throws RuntimeException If configuration format is invalid.
     */
    public function loadFromFile(string $path): array;

    /**
     * Loads operators from an array.
     *
     * Converts a PHP array structure into operators. Useful for programmatic
     * configuration and testing.
     *
     * @param array $operators Raw configuration array of operators.
     * @return array<string,OperatorInterface> Map of symbols to operators.
     * @throws RuntimeException If configuration format is invalid.
     */
    public function loadFromArray(array $operators): array;

    /**
     * Validates a set of operator.
     *
     * Ensures all operators have required fields and valid values. Also checks
     * for consistency across operators.
     *
     * @param array<string,OperatorInterface> $operators Operators to validate.
     * @throws RuntimeException If any validation fails.
     */
    public function validate(array $operators): void;
}
