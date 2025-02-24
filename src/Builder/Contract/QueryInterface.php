<?php

declare(strict_types=1);

/**
 * Derafu: Query - Smart Query Builder.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Builder\Contract;

/**
 * Interface for built queries.
 *
 * This interface defines the contract for query objects that are produced by
 * query builders. Each implementation can return its query in the appropriate
 * format for its target system (e.g., SQL string and parameters for raw SQL,
 * QueryBuilder for Doctrine, etc.).
 *
 * The interface uses a mixed return type for getQuery() to allow for different
 * query representations depending on the implementation.
 *
 * @example
 * ```php
 * // SQL implementation might return array with SQL and parameters.
 * $query->getQuery(); // ['sql' => 'SELECT...', 'parameters' => [...]]
 *
 * // Doctrine implementation might return QueryBuilder.
 * $query->getQuery(); // \Doctrine\ORM\QueryBuilder instance.
 * ```
 */
interface QueryInterface
{
    /**
     * Gets the built query.
     *
     * Returns the query in the format appropriate for the specific
     * implementation. This could be:
     *
     *   - An array with SQL and parameters for raw SQL queries.
     *   - A QueryBuilder instance for Doctrine queries.
     *   - Any other format required by the target system.
     *
     * @return mixed The query in implementation-specific format.
     */
    public function getQuery(): mixed;
}
