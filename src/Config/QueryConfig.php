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
use Derafu\Query\Filter\Contract\CompositeConditionInterface;
use Derafu\Query\Filter\Contract\ConditionInterface;
use Derafu\Support\ArrayReader;
use Derafu\Support\ArrayType;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;

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

    private readonly ArrayReader $reader;

    /**
     * Creates a new query configuration.
     *
     * @param array<string, mixed> $config The query configuration data.
     * @throws InvalidArgumentException If the configuration is invalid.
     */
    public function __construct(array $config)
    {
        $this->reader = ArrayReader::from($config);
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
            default => throw new InvalidArgumentException([
                'Unsupported file extension: {extension}',
                'extension' => $extension,
            ])
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
        // The table is optional: it can also be inferred from condition paths.
        if (($table = $this->reader->string('table')) !== null) {
            $alias = $this->reader->string('alias');
            $builder = $alias !== null
                ? $builder->table($table, $alias)
                : $builder->table($table);
        }

        if (($columns = $this->readColumns('select')) !== null) {
            $builder->select($columns);
        }

        if ($this->reader->bool('distinct') === true) {
            $builder->distinct();
        }

        if (($condition = $this->readCondition('where')) !== null) {
            $builder->where($condition);
        }
        if (($condition = $this->readCondition('andWhere')) !== null) {
            $builder->andWhere($condition);
        }
        if (($condition = $this->readCondition('orWhere')) !== null) {
            $builder->orWhere($condition);
        }
        if (($condition = $this->readConditionGroup('andWhereOr')) !== null) {
            $builder->andWhereOr($condition);
        }

        if (($join = $this->reader->reader('innerJoin')) !== null) {
            $builder->innerJoin(
                $join->require('table', ArrayType::string()),
                $join->require('condition', ArrayType::string()),
                $join->string('alias')
            );
        }
        if (($join = $this->reader->reader('leftJoin')) !== null) {
            $builder->leftJoin(
                $join->require('table', ArrayType::string()),
                $join->require('condition', ArrayType::string()),
                $join->string('alias')
            );
        }
        if (($join = $this->reader->reader('rightJoin')) !== null) {
            $builder->rightJoin(
                $join->require('table', ArrayType::string()),
                $join->require('condition', ArrayType::string()),
                $join->string('alias')
            );
        }
        if (($join = $this->reader->reader('crossJoin')) !== null) {
            $builder->crossJoin(
                $join->require('table', ArrayType::string()),
                $join->string('alias')
            );
        }

        if (($columns = $this->readColumns('groupBy')) !== null) {
            $builder->groupBy($columns);
        }
        if (($condition = $this->readCondition('having')) !== null) {
            $builder->having($condition);
        }
        if (($columns = $this->readColumns('orderBy')) !== null) {
            $builder->orderBy($columns);
        }
        if (($limit = $this->reader->int('limit')) !== null) {
            $builder->limit($limit);
        }
        if (($offset = $this->reader->int('offset')) !== null) {
            $builder->offset($offset);
        }

        return $builder;
    }

    /**
     * Validates known options before any query builder is changed.
     *
     * Unknown options are preserved. Absent and null options are not applied.
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

        $this->reader->string('table');
        $this->reader->string('alias');
        $this->reader->bool('distinct');
        $this->reader->int('limit');
        $this->reader->int('offset');

        foreach (['select', 'groupBy', 'orderBy'] as $key) {
            $this->readColumns($key);
        }
        foreach (['where', 'andWhere', 'orWhere', 'having'] as $key) {
            $this->readCondition($key);
        }
        $this->readConditionGroup('andWhereOr');

        foreach (['innerJoin', 'leftJoin', 'rightJoin', 'crossJoin'] as $key) {
            $join = $this->reader->reader($key);
            if ($join === null) {
                continue;
            }
            $join->require('table', ArrayType::string());
            if ($key !== 'crossJoin') {
                $join->require('condition', ArrayType::string());
            }
            $join->string('alias');
        }
    }

    /**
     * @return string|array<string>|null
     */
    private function readColumns(string $key): string|array|null
    {
        return $this->reader->get(
            $key,
            new ArrayType('string|array<string>', self::parseColumns(...))
        );
    }

    /**
     * Accepts both lists and keyed arrays, preserving their keys and values.
     *
     * @return string|array<string>
     */
    private static function parseColumns(mixed $value, string $path): string|array
    {
        if (is_string($value)) {
            return $value;
        }
        if (!is_array($value)) {
            throw ArrayType::mismatch($path, 'string|array<string>', $value);
        }

        $columns = [];
        foreach ($value as $key => $column) {
            $columns[$key] = ArrayType::string()->parse(
                $column,
                ArrayType::path($path, $key)
            );
        }

        return $columns;
    }

    /**
     * @return string|array<mixed>|ConditionInterface|CompositeConditionInterface|null
     */
    private function readCondition(
        string $key
    ): string|array|ConditionInterface|CompositeConditionInterface|null {
        return $this->reader->get(
            $key,
            new ArrayType(
                'string|array|ConditionInterface|CompositeConditionInterface',
                self::parseCondition(...)
            )
        );
    }

    /**
     * Unlike the other condition options, andWhereOr takes a group or an object.
     *
     * @return array<mixed>|ConditionInterface|CompositeConditionInterface|null
     */
    private function readConditionGroup(
        string $key
    ): array|ConditionInterface|CompositeConditionInterface|null {
        $condition = $this->readCondition($key);
        if (is_string($condition)) {
            throw ArrayType::mismatch(
                $key,
                'array|ConditionInterface|CompositeConditionInterface',
                $condition
            );
        }

        return $condition;
    }

    /**
     * Checks nested expressions without changing their grouping or keys.
     *
     * @return string|array<mixed>|ConditionInterface|CompositeConditionInterface
     */
    private static function parseCondition(
        mixed $value,
        string $path
    ): string|array|ConditionInterface|CompositeConditionInterface {
        if (
            is_string($value)
            || $value instanceof ConditionInterface
            || $value instanceof CompositeConditionInterface
        ) {
            return $value;
        }
        if (!is_array($value)) {
            throw ArrayType::mismatch(
                $path,
                'string|array|ConditionInterface|CompositeConditionInterface',
                $value
            );
        }

        $conditions = [];
        foreach ($value as $key => $condition) {
            $conditions[$key] = self::parseCondition(
                $condition,
                ArrayType::path($path, $key)
            );
        }

        return $conditions;
    }
}
