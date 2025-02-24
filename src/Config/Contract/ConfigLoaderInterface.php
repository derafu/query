<?php

declare(strict_types=1);

/**
 * Derafu: Query - Smart Query Builder.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Config\Contract;

use InvalidArgumentException;
use RuntimeException;

/**
 * Interface for configuration loaders.
 *
 * Defines methods for loading query configurations from different sources.
 */
interface ConfigLoaderInterface
{
    /**
     * Loads a configuration from a file.
     *
     * @param string $filePath Path to the configuration file.
     * @return array<string, mixed> The loaded configuration.
     * @throws RuntimeException If the file cannot be read.
     * @throws InvalidArgumentException If the file contains invalid configuration.
     */
    public function loadFromFile(string $filePath): array;

    /**
     * Loads a configuration from a string.
     *
     * @param string $content String containing the configuration.
     * @return array<string, mixed> The loaded configuration.
     * @throws InvalidArgumentException If the string contains invalid configuration.
     */
    public function loadFromString(string $content): array;
}
