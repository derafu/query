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
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * YAML configuration loader.
 *
 * Loads query configurations from YAML files or strings.
 */
class YamlConfigLoader implements ConfigLoaderInterface
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
            $config = Yaml::parse($content);
            if (!is_array($config)) {
                throw new InvalidArgumentException(
                    'Invalid YAML configuration format.'
                );
            }
            return $config;
        } catch (ParseException $e) {
            throw new InvalidArgumentException(
                "Error parsing YAML file: {$e->getMessage()}",
                0,
                $e
            );
        }
    }
}
