<?php

declare(strict_types=1);

/**
 * Derafu: Query - Smart Query Builder.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Operator;

use Derafu\Query\Operator\Contract\OperatorConfigInterface;
use Derafu\Query\Operator\Contract\OperatorConfigLoaderInterface;
use Exception;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * Loads operator configurations from YAML files and arrays.
 *
 * This class handles loading and validating operator configurations from
 * various sources. It ensures configurations meet required structure and
 * creates the corresponding configuration objects.
 */
final class OperatorConfigLoader implements OperatorConfigLoaderInterface
{
    /**
     * Required operator configuration fields and their types.
     *
     * @var array<string,string>
     */
    private const REQUIRED_FIELDS = [
        'type' => 'string',
        'name' => 'string',
        'description' => 'string',
    ];

    /**
     * Loads operator configurations from a YAML file.
     *
     * Reads and parses a YAML file containing operator configurations.
     * Validates the structure and creates configuration objects.
     *
     * @param string $path Path to the YAML configuration file.
     * @return array<string,OperatorConfigInterface> Map of operator configs.
     * @throws InvalidArgumentException If file cannot be read.
     * @throws RuntimeException If configuration format is invalid.
     */
    public function loadFromFile(string $path): array
    {
        if (!file_exists($path)) {
            throw new InvalidArgumentException(
                sprintf('Configuration file not found: %s', $path)
            );
        }

        try {
            $content = Yaml::parseFile($path);
        } catch (Exception $e) {
            throw new RuntimeException(
                sprintf('Failed to parse YAML file: %s', $e->getMessage())
            );
        }

        return $this->loadFromArray($content);
    }

    /**
     * Loads operator configurations from an array.
     *
     * Transforms a raw configuration array into operator configuration objects,
     * validating structure and required fields.
     *
     * @param array $config Raw configuration array.
     * @return array<string,OperatorConfigInterface> Map of operator configs.
     * @throws RuntimeException If configuration format is invalid.
     */
    public function loadFromArray(array $config): array
    {
        if (!isset($config['types']) || !isset($config['operators'])) {
            throw new RuntimeException(
                'Configuration must contain "types" and "operators" sections.'
            );
        }

        $operators = [];
        foreach ($config['operators'] as $symbol => $operatorConfig) {
            $type = $operatorConfig['type'] ?? null;
            if ($type && !isset($config['types'][$type])) {
                throw new RuntimeException(
                    sprintf('Undefined operator type: %s', $type)
                );
            }

            $operators[$symbol] = new OperatorConfig(
                symbol: $symbol,
                config: $operatorConfig
            );
        }

        $this->validate($operators);

        return $operators;
    }

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
    public function validate(array $configs): void
    {
        foreach ($configs as $symbol => $config) {
            // Check symbol matches configuration.
            if ($symbol !== $config->getSymbol()) {
                throw new RuntimeException(
                    sprintf(
                        'Symbol mismatch: Expected "%s" but got "%s" in configuration.',
                        $symbol,
                        $config->getSymbol()
                    )
                );
            }

            // Validate required fields.
            foreach (self::REQUIRED_FIELDS as $field => $type) {
                $value = $config->get($field);
                if ($value === null) {
                    throw new RuntimeException(
                        sprintf(
                            'Missing required field "%s" for operator "%s".',
                            $field,
                            $symbol
                        )
                    );
                }
                if (gettype($value) !== $type) {
                    throw new RuntimeException(
                        sprintf(
                            'Invalid type for field "%s" in operator "%s": Expected "%s" but got "%s".',
                            $field,
                            $symbol,
                            $type,
                            gettype($value)
                        )
                    );
                }
            }

            // Validate SQL templates.
            $templates = $config->getSqlTemplates();
            if (empty($templates)) {
                throw new RuntimeException(
                    sprintf(
                        'No SQL templates defined for operator "%s".',
                        $symbol
                    )
                );
            }
        }
    }
}
