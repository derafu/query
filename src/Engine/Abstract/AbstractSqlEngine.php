<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Engine\Abstract;

use Derafu\Query\Engine\Contract\SqlEngineInterface;

/**
 * Abstract SQL engine implementation.
 *
 * This class provides a base implementation for SQL engine operations.
 * It includes methods for executing queries, fetching rows, and handling
 * common database operations.
 */
abstract class AbstractSqlEngine implements SqlEngineInterface
{
    /**
     * {@inheritDoc}
     */
    public function getTable(string $sql, array $parameters = []): array
    {
        return $this->execute($sql, $parameters);
    }

    /**
     * {@inheritDoc}
     */
    public function getRow(string $sql, array $parameters = []): array
    {
        $rows = $this->execute($sql, $parameters);

        return $rows[0] ?? [];
    }

    /**
     * {@inheritDoc}
     */
    public function getCol(string $sql, array $parameters = []): array
    {
        $rows = $this->execute($sql, $parameters);

        return array_column($rows, array_key_first($rows[0] ?? []));
    }

    /**
     * {@inheritDoc}
     */
    public function getValue(string $sql, array $parameters = []): mixed
    {
        $row = $this->getRow($sql, $parameters);

        return $row ? array_shift($row) : null;
    }
}
