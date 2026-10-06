<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Config;

use Derafu\Query\Config\Contract\ConfigLoaderInterface;
use Derafu\Translation\Exception\Core\TranslatableRuntimeException as RuntimeException;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;
use JsonException;

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
            throw new RuntimeException([
                'Cannot read configuration file: {path}',
                'path' => $filePath,
            ]);
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new RuntimeException(['Failed to read file: {path}', 'path' => $filePath]);
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
                ['Error parsing JSON: {error}', 'error' => $e->getMessage()],
                0,
                $e
            );
        }
    }
}
