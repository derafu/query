<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Builder\Sql;

use Closure;

/**
 * Provides methods to sanitize SQL identifiers and expressions.
 *
 * This trait handles:
 *
 *   - Simple identifiers: column_name.
 *   - Qualified identifiers: table.column.
 *   - SQL functions: COUNT(*), SUM(column).
 *   - Expressions with alias: column AS alias.
 */
trait SqlSanitizerTrait
{
    /**
     * The official function to sanitize/quote SQL identifiers.
     *
     * @var Closure|null
     */
    private ?Closure $sanitizeSqlIdentifierCallback = null;

    /**
     * Sets the official function to sanitize/quote SQL identifiers.
     *
     * @param Closure $sanitizeSqlIdentifierCallback
     * @return self
     */
    public function setSanitizeSqlIdentifierCallback(
        Closure $sanitizeSqlIdentifierCallback
    ): self {
        $this->sanitizeSqlIdentifierCallback = $sanitizeSqlIdentifierCallback;

        return $this;
    }

    /**
     * Sanitizes a SQL identifier or expression.
     *
     * @param string $expression The SQL expression to sanitize.
     * @return string The sanitized expression.
     */
    private function sanitizeSqlIdentifier(string $expression): string
    {
        // Handle expressions with AS (aliases).
        if (preg_match('/^(.+)\s+AS\s+(.+)$/i', $expression, $matches)) {
            $expr = trim($matches[1]);
            $alias = trim($matches[2]);

            // Remove any quotes from the alias if they exist.
            $alias = trim($alias, '"\'`[]');

            return $this->sanitizeSqlExpression($expr)
                . ' AS ' . $this->sanitizeSqlSimpleIdentifier($alias)
            ;
        }

        // If no AS keyword, process as a regular expression.
        return $this->sanitizeSqlExpression($expression);
    }

    /**
     * Sanitizes a SQL expression which may contain functions.
     *
     * @param string $expression The SQL expression to sanitize.
     * @return string The sanitized expression.
     */
    private function sanitizeSqlExpression(string $expression): string
    {
        // Asterisk is a special case.
        if ($expression === '*') {
            return '*';
        }

        // Check if it's a function call like COUNT(*), AVG(column), etc.
        if (preg_match('/^([A-Za-z0-9_]+)\s*\((.*)\)$/i', $expression, $matches)) {
            $functionName = $matches[1];
            $arguments = $matches[2];

            // Check if this is an aggregate function that needs special handling.
            $isAggregateFunction = in_array(
                strtoupper($functionName),
                ['COUNT', 'MIN', 'MAX', 'AVG', 'SUM']
            );

            // Get sanitized function name.
            $sanitizedFunctionName = $this->sanitizeSqlSimpleIdentifier($functionName);

            // Special case for aggregate functions with callback.
            if ($isAggregateFunction && $this->sanitizeSqlIdentifierCallback !== null) {
                // For aggregate functions with callback, we want to preserve
                // the original function name.
                $sanitizedFunctionName = $functionName;
            }

            // Special case for * in aggregate functions.
            if (trim($arguments) === '*') {
                return "$sanitizedFunctionName(*)";
            }

            // Handle multiple arguments.
            $args = array_map('trim', explode(',', $arguments));
            $sanitizedArgs = [];

            foreach ($args as $arg) {
                // If argument seems like an identifier (not a string, number or
                // expression).
                if (
                    !preg_match('/^([\'"]).*\1$/', $arg)
                    && !is_numeric($arg)
                    && !str_contains($arg, '+')
                    && !str_contains($arg, '-')
                    && !str_contains($arg, '*')
                    && !str_contains($arg, '/')
                ) {
                    $sanitizedArgs[] = $this->sanitizeSqlExpression($arg);
                }
                // Keep as is (for numbers, strings, expressions).
                else {
                    $sanitizedArgs[] = $arg;
                }
            }

            return "$sanitizedFunctionName(" . implode(', ', $sanitizedArgs) . ")";
        }

        // If it contains a dot, it might be a qualified identifier.
        if (str_contains($expression, '.')) {
            $parts = explode('.', $expression);
            $allPartsValid = true;
            foreach ($parts as $part) {
                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $part)) {
                    $allPartsValid = false;
                    break;
                }
            }

            if ($allPartsValid) {
                return implode('.', array_map([$this, 'sanitizeSqlSimpleIdentifier'], $parts));
            }
        }

        // Simple identifier.
        return $this->sanitizeSqlSimpleIdentifier($expression);
    }

    /**
     * Sanitizes a simple identifier (column or table name).
     *
     * @param string $identifier The simple identifier to sanitize.
     * @return string The sanitized identifier.
     */
    private function sanitizeSqlSimpleIdentifier(string $identifier): string
    {
        // Basic sanitization of simple identifiers.
        $identifier = preg_replace('/[^a-zA-Z0-9_]/', '', $identifier);

        if ($this->sanitizeSqlIdentifierCallback !== null) {
            return ($this->sanitizeSqlIdentifierCallback)($identifier);
        }

        return $identifier;
    }
}
