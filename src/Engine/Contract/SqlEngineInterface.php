<?php

declare(strict_types=1);

/**
 * Derafu: Query - Smart Query Builder.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Engine\Contract;

/**
 * Interface for SQL execution engines.
 *
 * This interface defines the contract for executing SQL queries and managing
 * database connections. Implementations should handle:
 *
 *   - Query execution.
 *   - Parameter binding.
 *   - Result fetching.
 *   - Error handling.
 */
interface SqlEngineInterface
{
    /**
     * Executes a SQL query with parameters and returns the results.
     *
     * @param string $sql The SQL query to execute.
     * @param array<string,mixed> $parameters Named parameters for the query.
     * @return array<array<string,mixed>> The query results as an array of rows.
     */
    public function execute(string $sql, array $parameters = []): array;

    /**
     * Gets the underlying database connection.
     *
     * @return mixed The database connection instance.
     */
    public function getConnection(): mixed;

    /**
     * Gets the driver name normalized to PDO driver name conventions.
     *
     * @return string
     */
    public function getDriver(): string;
}
