<?php

declare(strict_types=1);

/**
 * Derafu: Query - Smart Query Builder.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Operator\Contract;

use InvalidArgumentException;
use RuntimeException;

/**
 * Loader for operator configurations.
 *
 * This component handles loading operator configurations from various sources
 * and validating them before use. It supports both file-based and programmatic
 * configuration loading.
 */
interface OperatorConfigLoaderInterface
{
    /**
     * Loads operator configurations from a file.
     *
     * Reads a configuration file and converts it into operator configurations.
     * Supports YAML format for easy human editing of operator definitions.
     *
     * @param string $path Path to the configuration file.
     * @return array<string,OperatorConfigInterface> Map of symbols to configs.
     * @throws InvalidArgumentException If file cannot be read.
     * @throws RuntimeException If configuration format is invalid.
     */
    public function loadFromFile(string $path): array;

    /**
     * Loads operator configurations from an array.
     *
     * Converts a PHP array structure into operator configurations. Useful for
     * programmatic configuration and testing.
     *
     * @param array $config Raw configuration array.
     * @return array<string,OperatorConfigInterface> Map of symbols to configs.
     * @throws RuntimeException If configuration format is invalid.
     */
    public function loadFromArray(array $config): array;

    /**
     * Validates a set of operator configurations.
     *
     * Ensures all configurations have required fields and valid values. Also
     * checks for consistency across operators.
     *
     * @param array<string,OperatorConfigInterface> $configs Configurations to
     * validate.
     * @throws RuntimeException If any validation fails.
     */
    public function validate(array $configs): void;
}
