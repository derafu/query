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

use Derafu\Query\Builder\Contract\QueryBuilderInterface;
use Derafu\Query\Config\Contract\QueryConfigInterface;
use InvalidArgumentException;

/**
 * Query configuration.
 *
 * Represents a query configuration that can be applied to a query builder.
 */
class QueryConfig implements QueryConfigInterface
{
    /**
     * The query configuration data.
     *
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * Creates a new query configuration.
     *
     * @param array<string, mixed> $config The query configuration data.
     * @throws InvalidArgumentException If the configuration is invalid.
     */
    public function __construct(array $config)
    {
        $this->validateConfiguration($config);
        $this->config = $config;
    }

    /**
     * Creates a configuration from a YAML file.
     *
     * @param string $filePath Path to the YAML configuration file.
     * @return self The created configuration instance.
     */
    public static function fromYamlFile(string $filePath): self
    {
        $loader = new YamlConfigLoader();

        return new self($loader->loadFromFile($filePath));
    }

    /**
     * Creates a configuration from a YAML string.
     *
     * @param string $yaml YAML string containing the configuration.
     * @return self The created configuration instance.
     */
    public static function fromYamlString(string $yaml): self
    {
        $loader = new YamlConfigLoader();

        return new self($loader->loadFromString($yaml));
    }

    /**
     * Creates a configuration from a JSON file.
     *
     * @param string $filePath Path to the JSON configuration file.
     * @return self The created configuration instance.
     */
    public static function fromJsonFile(string $filePath): self
    {
        $loader = new JsonConfigLoader();

        return new self($loader->loadFromFile($filePath));
    }

    /**
     * Creates a configuration from a JSON string.
     *
     * @param string $json JSON string containing the configuration.
     * @return self The created configuration instance.
     */
    public static function fromJsonString(string $json): self
    {
        $loader = new JsonConfigLoader();

        return new self($loader->loadFromString($json));
    }

    /**
     * Creates a configuration from a file with automatic format detection.
     *
     * @param string $filePath Path to the configuration file.
     * @return self The created configuration instance.
     * @throws InvalidArgumentException If the file extension is not supported.
     */
    public static function fromFile(string $filePath): self
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);

        return match(strtolower($extension)) {
            'yml', 'yaml' => self::fromYamlFile($filePath),
            'json' => self::fromJsonFile($filePath),
            default => throw new InvalidArgumentException("Unsupported file extension: {$extension}")
        };
    }

    /**
     * {@inheritDoc}
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * {@inheritDoc}
     */
    public function applyTo(QueryBuilderInterface $builder): QueryBuilderInterface
    {
        // Configure FROM clause (required).
        if (isset($this->config['table'])) {
            $builder = isset($this->config['alias'])
                ? $builder->table($this->config['table'], $this->config['alias'])
                : $builder->table($this->config['table']);
        }

        // Configure SELECT columns.
        if (isset($this->config['select'])) {
            $builder->select($this->config['select']);
        }

        // Configure DISTINCT.
        if (isset($this->config['distinct']) && $this->config['distinct']) {
            $builder->distinct();
        }

        // Configure WHERE conditions.
        if (isset($this->config['where'])) {
            $builder->where($this->config['where']);
        }

        // Configure additional WHERE conditions.
        if (isset($this->config['andWhere'])) {
            $builder->andWhere($this->config['andWhere']);
        }

        if (isset($this->config['orWhere'])) {
            $builder->orWhere($this->config['orWhere']);
        }

        if (isset($this->config['andWhereOr'])) {
            $builder->andWhereOr($this->config['andWhereOr']);
        }

        // Configure JOIN clauses.
        if (isset($this->config['innerJoin'])) {
            $join = $this->config['innerJoin'];
            $builder->innerJoin(
                $join['table'],
                $join['condition'],
                $join['alias'] ?? null
            );
        }

        if (isset($this->config['leftJoin'])) {
            $join = $this->config['leftJoin'];
            $builder->leftJoin(
                $join['table'],
                $join['condition'],
                $join['alias'] ?? null
            );
        }

        if (isset($this->config['rightJoin'])) {
            $join = $this->config['rightJoin'];
            $builder->rightJoin(
                $join['table'],
                $join['condition'],
                $join['alias'] ?? null
            );
        }

        if (isset($this->config['crossJoin'])) {
            $join = $this->config['crossJoin'];
            $builder->crossJoin(
                $join['table'],
                $join['alias'] ?? null
            );
        }

        // Configure GROUP BY.
        if (isset($this->config['groupBy'])) {
            $builder->groupBy($this->config['groupBy']);
        }

        // Configure HAVING.
        if (isset($this->config['having'])) {
            $builder->having($this->config['having']);
        }

        // Configure ORDER BY.
        if (isset($this->config['orderBy'])) {
            $builder->orderBy($this->config['orderBy']);
        }

        // Configure LIMIT and OFFSET.
        if (isset($this->config['limit'])) {
            $builder->limit($this->config['limit']);
        }

        if (isset($this->config['offset'])) {
            $builder->offset($this->config['offset']);
        }

        return $builder;
    }

    /**
     * Validates that the configuration contains the minimum required elements.
     *
     * For now, just validates emptiness.
     *
     * @param array<string, mixed> $config The configuration to validate.
     * @throws InvalidArgumentException If the configuration is invalid.
     */
    private function validateConfiguration(array $config): void
    {
        if (empty($config)) {
            throw new InvalidArgumentException(
                'Query configuration cannot be empty.'
            );
        }
    }
}
