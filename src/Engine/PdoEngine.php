<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Engine;

use Derafu\Query\Engine\Abstract\AbstractSqlEngine;
use Derafu\Query\Engine\Contract\SqlEngineInterface;
use PDO;

/**
 * PDO implementation of the SQL execution engine.
 *
 * This class provides a PDO-based implementation for executing SQL queries.
 *
 * It handles:
 *
 *   - Safe parameter binding.
 *   - Result fetching in associative array format.
 *   - Connection management.
 *   - Error handling through PDO's exception mode.
 */
final class PdoEngine extends AbstractSqlEngine implements SqlEngineInterface
{
    /**
     * Creates a new PDO engine instance.
     *
     * @param PDO $connection An existing PDO connection instance.
     */
    public function __construct(
        private readonly PDO $connection
    ) {
        // Ensure exceptions are thrown on error.
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * {@inheritDoc}
     */
    public function execute(string $sql, array $parameters = []): array
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($parameters);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * {@inheritDoc}
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }

    /**
     * {@inheritDoc}
     */
    public function getDriver(): string
    {
        return $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    /**
     * {@inheritDoc}
     */
    public function executeSqlDump(string $sql): void
    {
        $this->connection->exec($sql);
    }
}
