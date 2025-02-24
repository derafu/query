<?php

declare(strict_types=1);

/**
 * Derafu: Query - Smart Query Builder.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Builder;

use Closure;
use Derafu\Query\Builder\Contract\QueryBuilderInterface;
use Derafu\Query\Builder\Contract\QueryInterface;
use Derafu\Query\Filter\Contract\FilterInterface;
use Derafu\Query\Filter\Contract\PathInterface;
use InvalidArgumentException;
use UnderflowException;

/**
 * SQL query builder implementation.
 *
 * This builder generates SQL queries from paths and filters, handling:
 *
 *   - SQL identifier quoting and escaping.
 *   - Value normalization and casting.
 *   - Multiple placeholder formats (single, paired, lists).
 *   - Different SQL templates per database engine.
 */
final class SqlQueryBuilder implements QueryBuilderInterface
{
    /**
     * Create a new SQL Query Builder.
     *
     * @param string $engine The database engine for SQL generation.
     * @param Closure $quoteIdentifierCallback The function to quote SQL
     * identifiers.
     * @param string $listDelimiter Delimiter used when processing list values.
     */
    public function __construct(
        private readonly string $engine = 'pgsql',
        private readonly ?Closure $quoteIdentifierCallback = null,
        private readonly string $listDelimiter = ','
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function build(
        PathInterface $path,
        FilterInterface $filter
    ): QueryInterface {
        // Get operator and value.
        $operator = $filter->getOperator();
        $baseOperator = $operator->getBaseOperator();

        // Validate value against pattern if exists.
        $value = $filter->getValue();
        $pattern = $operator->getValidationPattern()
            ?? $baseOperator?->getValidationPattern()
        ;
        if ($pattern !== null && $value !== null) {
            if (!preg_match($pattern, $value)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Invalid value format for operator %s: %s.',
                        $operator->getSymbol(),
                        $value
                    )
                );
            }
        }

        // Normalize and cast value.
        $value = $this->normalizeValue($value);
        $value = $this->castValue(
            $value,
            (array)($operator->getCastingRules() ?: $baseOperator?->getCastingRules())
        );

        // Get SQL template for the engine.
        $templates = $operator->get('sql') ?? $baseOperator?->get('sql');
        if (is_string($templates)) {
            $sql = $templates;
        } else {
            $sql = $templates[$this->engine] ?? null;
        }

        if (empty($sql)) {
            throw new InvalidArgumentException(
                sprintf(
                    'No SQL template for operator %s on engine %s.',
                    $operator->getSymbol(),
                    $this->engine
                )
            );
        }

        // Create parameters for the query.
        $parameters = $this->createParameters($value, $path->getColumn());

        // Build the WHERE clause.
        return new SqlQuery(...$this->createWhere(
            $sql,
            $this->buildColumnFromPath($path),
            $parameters
        ));
    }

    /**
     * Normalizes a value ensuring it's a valid string for SQL.
     *
     * Handles:
     *
     *   - Boolean values to 1/0.
     *   - Arrays to delimited lists.
     *   - NULL values.
     *
     * @param mixed $value Raw value to normalize.
     * @return string|null Normalized string value.
     */
    private function normalizeValue(mixed $value): ?string
    {
        if (is_bool($value)) {
            return (string)(int)$value;
        }

        if (is_array($value)) {
            return implode($this->listDelimiter, array_map(
                fn ($v) => str_replace(
                    $this->listDelimiter,
                    '\\' . $this->listDelimiter,
                    (string)$v
                ),
                $value
            ));
        }

        return $value;
    }

    /**
     * Applies casting rules to a value.
     *
     * @param string|null $value The value to cast.
     * @param array $rules The casting rules to apply.
     * @return string|array|null The casted value.
     */
    private function castValue(?string $value, array $rules): string|array|null
    {
        if ($value === null || empty($rules)) {
            return $value;
        }

        foreach ($rules as $rule) {
            switch ($rule) {
                case 'like_start':
                    $value .= '%';
                    break;
                case 'like':
                    $value = '%' . $value . '%';
                    break;
                case 'like_end':
                    $value = '%' . $value;
                    break;
                case 'list':
                    $value = explode($this->listDelimiter, $value);
                    break;
                case 'date':
                    if (strlen($value) === 8) {
                        // YYYYMMDD to YYYY-MM-DD.
                        $value = substr($value, 0, 4) . '-'
                            . substr($value, 4, 2) . '-' . substr($value, 6, 2)
                        ;
                    } else {
                        // YYMMDD to YYYY-MM-DD (asume 20YY).
                        $value = '20' . substr($value, 0, 2) . '-'
                            . substr($value, 2, 2) . '-' . substr($value, 4, 2)
                        ;
                    }
                    break;

                case 'month':
                    // Asegura que el mes tenga 2 dígitos (8 -> 08)
                    $value = str_pad($value, 2, '0', STR_PAD_LEFT);
                    break;

                case 'year':
                    if (strlen($value) === 2) {
                        // YY to YYYY (asume 20YY).
                        $value = '20' . $value;
                    }
                    break;

                case 'period':
                    if (strlen($value) === 6) {
                        // YYYYMM se deja tal cual.
                        $value = $value;
                    } else {
                        // YYMM to YYYYMM (asume 20YY).
                        $value = '20' . $value;
                    }
                    break;
            }
        }

        return $value;
    }

    /**
     * Creates SQL parameters from a value.
     *
     * @param string|array|null $value The value to parameterize.
     * @param string $column The column name for parameter prefixing.
     * @return array The parameters array.
     */
    private function createParameters(string|array|null $value, string $column): array
    {
        $id = str_replace('.', '_', uniqid($column . '_'));
        $placeholder = 'param_' . $id;

        if (is_array($value)) {
            $parameters = [];
            foreach ($value as $i => $v) {
                $parameters[$placeholder . '_' . ($i + 1)] = $v;
            }
            return $parameters;
        }

        return [$placeholder => $value];
    }

    /**
     * Creates the WHERE clause from SQL template and parameters.
     *
     * Handles different placeholder formats:
     *
     *   - {{value}} for single values.
     *   - {{value_1}} and {{value_2}} for paired values.
     *   - {{values}} for lists.
     *
     * @param string $sql Base SQL template.
     * @param string $column The column name.
     * @param array $parameters Query parameters.
     * @return array{sql: string, parameters: array} The complete WHERE clause.
     */
    private function createWhere(string $sql, string $column, array $parameters): array
    {
        // Replace the column.
        $sql = str_replace('{{column}}', $column, $sql);

        // // No parameters needed for NULL operators.
        if (str_contains($sql, 'IS NULL') || str_contains($sql, 'IS NOT NULL')) {
            return [
                'sql' => $sql,
                'parameters' => [],
            ];
        }

        $parameterKeys = array_map(
            fn ($key) => ':' . $key,
            array_keys($parameters)
        );

        // Replace basic placeholders.
        $sql = strtr($sql, [
            '{{column}}' => $column,
            '{{operator}}' => '', // Not used in current implementation.
        ]);

        // Handle single value.
        if (str_contains($sql, '{{value}}')) {
            if (!isset($parameterKeys[0])) {
                throw new UnderflowException('Missing parameter for {{value}}.');
            }
            $sql = str_replace('{{value}}', $parameterKeys[0], $sql);
        }

        // Handle paired values.
        elseif (str_contains($sql, '{{value_1}}')) {
            if (!isset($parameterKeys[0], $parameterKeys[1])) {
                throw new UnderflowException(
                    'Missing parameters for {{value_1}} or {{value_2}}.'
                );
            }
            $sql = strtr($sql, [
                '{{value_1}}' => $parameterKeys[0],
                '{{value_2}}' => $parameterKeys[1],
            ]);
        }

        // Handle value lists.
        elseif (str_contains($sql, '{{values}}')) {
            if (empty($parameterKeys)) {
                throw new UnderflowException('Missing parameters for {{values}}.');
            }
            $sql = str_replace('{{values}}', implode(', ', $parameterKeys), $sql);
        }

        return [
            'sql' => $sql,
            'parameters' => $parameters,
        ];
    }

    /**
     * Builds a fully qualified column name from a path.
     *
     * @param PathInterface $path The path to convert to a column name.
     * @return string The sanitized column name.
     */
    private function buildColumnFromPath(PathInterface $path): string
    {
        // TODO: Handle joins and aliases
        $column = $path->getColumn();
        return $this->quoteIdentifier($column);
    }

    /**
     * Sanitizes a SQL identifier.
     *
     * @param string $identifier The identifier to sanitize.
     * @return string The sanitized identifier.
     */
    private function quoteIdentifier(string $identifier): string
    {
        $identifier = preg_replace('/[^a-zA-Z0-9_.]/', '', $identifier);

        if (isset($this->quoteIdentifierCallback)) {
            return call_user_func($this->quoteIdentifierCallback, $identifier);
        }

        return $identifier;
    }
}
