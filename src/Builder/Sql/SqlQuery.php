<?php

declare(strict_types=1);

/**
 * Derafu: Query - Smart Query Builder.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Builder\Sql;

use ArrayAccess;
use Derafu\Query\Builder\Contract\QueryInterface;
use InvalidArgumentException;
use LogicException;

/**
 * SQL query implementation.
 *
 * This class holds the generated SQL query and its parameters.
 */
final class SqlQuery implements QueryInterface, ArrayAccess
{
    /**
     * Creates a new SQL query instance.
     *
     * @param string $sql The SQL query string.
     * @param array<string,mixed> $parameters The query parameters.
     */
    public function __construct(
        private readonly string $sql,
        private readonly array $parameters
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @return array{sql: string, parameters: array<string,mixed>} The SQL query
     * and parameters.
     */
    public function getQuery(): array
    {
        return [
            'sql' => $this->sql,
            'parameters' => $this->parameters,
        ];
    }

    public function offsetExists(mixed $offset): bool
    {
        return in_array($offset, ['sql', 'parameters']);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return match ($offset) {
            'sql' => $this->sql,
            'parameters' => $this->parameters,
            default => throw new InvalidArgumentException(sprintf(
                'Key %s does not exists.',
                $offset
            ))
        };
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('SQL Query data is immutable.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('SQL Query data is immutable.');
    }
}
