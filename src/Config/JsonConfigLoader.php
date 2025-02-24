<?php

declare(strict_types=1);

/**
 * Derafu: Query - Smart Query Builder.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Config;

use Derafu\Query\Config\Contract\ConfigLoaderInterface;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

/**
 * JSON configuration loader.
 *
 * Loads query configurations from JSON files or strings.
 */
class JsonConfigLoader implements ConfigLoaderInterface
{
    /**
     * {@inheritDoc}
     */
    public function loadFromFile(string $filePath): array
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new RuntimeException(
                "Cannot read configuration file: {$filePath}"
            );
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new RuntimeException("Failed to read file: {$filePath}");
        }

        return $this->loadFromString($content);
    }

    /**
     * {@inheritDoc}
     */
    public function loadFromString(string $content): array
    {
        try {
            $config = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($config)) {
                throw new InvalidArgumentException(
                    'Invalid JSON configuration format.'
                );
            }
            return $config;
        } catch (JsonException $e) {
            throw new InvalidArgumentException(
                "Error parsing JSON: {$e->getMessage()}",
                0,
                $e
            );
        }
    }
}
