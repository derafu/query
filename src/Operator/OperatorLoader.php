<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Operator;

use Derafu\Array\ArrayReader;
use Derafu\Query\Operator\Contract\OperatorLoaderInterface;
use Derafu\Translation\Exception\Core\TranslatableRuntimeException as RuntimeException;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;
use Derafu\Value\ValueType;
use Exception;
use Symfony\Component\Yaml\Yaml;

/**
 * Loads operators from YAML files and arrays.
 *
 * This class handles loading and validating operators from various sources. It
 * ensures configurations meet required structure and creates the corresponding
 * configuration objects.
 */
final class OperatorLoader implements OperatorLoaderInterface
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
     * {@inheritDoc}
     */
    public function loadFromFile(string $path): array
    {
        if (!file_exists($path)) {
            throw new InvalidArgumentException(
                ['Configuration file not found: {path}', 'path' => $path]
            );
        }

        try {
            $content = Yaml::parseFile($path);
        } catch (Exception $e) {
            throw new RuntimeException(
                ['Failed to parse YAML file: {error}', 'error' => $e->getMessage()]
            );
        }

        return $this->loadFromArray(ValueType::array()->parse($content, 'configuration', $path));
    }

    /**
     * {@inheritDoc}
     *
     * @param array<array-key,mixed> $config Raw operator configuration.
     */
    public function loadFromArray(array $config): array
    {
        if (!isset($config['types']) || !isset($config['operators'])) {
            throw new RuntimeException(
                'Configuration must contain "types" and "operators" sections.'
            );
        }

        $reader = new ArrayReader($config);
        $types = $reader->require('types', ValueType::array());
        $definitions = $reader->require('operators', ValueType::array());

        $operators = [];
        foreach ($definitions as $symbol => $operatorConfig) {
            $symbol = ValueType::string()->parse($symbol, 'operators.symbol');
            $operatorConfig = ValueType::array()->parse($operatorConfig, 'operators.' . $symbol);
            $operatorReader = new ArrayReader($operatorConfig, $symbol);
            $type = $operatorReader->getString('type');
            if ($type && !isset($types[$type])) {
                throw new RuntimeException(
                    ['Undefined operator type: {type}.', 'type' => (string) $type]
                );
            }

            $use = $operatorReader->getString('alias');
            if ($use !== null) {
                if (!isset($operators[$use])) {
                    throw new InvalidArgumentException(
                        [
                            'Operator {symbol} requires unloaded operator: {required}.',
                            'symbol' => (string) $symbol,
                            'required' => (string) $use,
                        ]
                    );
                }

                $use = $operators[$use];
            }

            $operators[$symbol] = new Operator(
                symbol: $symbol,
                config: $operatorConfig,
                baseOperator: $use
            );
        }

        $this->validate($operators);

        return $operators;
    }

    /**
     * {@inheritDoc}
     */
    public function validate(array $operators): void
    {
        foreach ($operators as $symbol => $operator) {
            // Check symbol matches configuration.
            if ($symbol !== $operator->getSymbol()) {
                throw new RuntimeException(
                    [
                        'Symbol mismatch: Expected "{expected}" but got "{actual}" in configuration.',
                        'expected' => (string) $symbol,
                        'actual' => $operator->getSymbol(),
                    ]
                );
            }

            // Validate required fields.
            foreach (self::REQUIRED_FIELDS as $field => $type) {
                $value = $operator->get($field);
                if ($value === null) {
                    throw new RuntimeException(
                        [
                            'Missing required field "{field}" for operator "{operator}".',
                            'field' => (string) $field,
                            'operator' => (string) $symbol,
                        ]
                    );
                }
                if (gettype($value) !== $type) {
                    throw new RuntimeException(
                        [
                            'Invalid type for field "{field}" in operator "{operator}": Expected "{expected}" but got "{actual}".',
                            'field' => (string) $field,
                            'operator' => (string) $symbol,
                            'expected' => (string) $type,
                            'actual' => gettype($value),
                        ]
                    );
                }
            }

            ValueType::string()->nullable()->parse($operator->get('alias'), 'alias', $symbol);
            $operator->getValidationPattern();
            $operator->getCastingRules();
        }
    }
}
