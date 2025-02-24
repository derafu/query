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

use Derafu\Query\Filter\Contract\FilterInterface;
use Derafu\Query\Filter\Contract\PathInterface;

/**
 * Interface for query builders.
 *
 * This interface defines the contract for building queries from a path and
 * filter combination. The path represents the navigation through relations to
 * reach a target column, while the filter defines the conditions to apply on
 * that column.
 *
 * Query builders take these high-level representations and transform them into
 * executable queries in their respective target systems (SQL, ORM, etc.).
 *
 * @example
 * ```php
 * // Build a query to find books where the author's name is 'John'.
 * $path = new Path(['author', 'name']);
 * $filter = new Filter($equalsOperator, 'John');
 * $query = $builder->build($path, $filter);
 * ```
 */
interface QueryBuilderInterface
{
    /**
     * Builds a query from a path and filter.
     *
     * Takes a path that represents the navigation through relations to reach a
     * target column, and a filter that defines the conditions to apply on that
     * column. Returns a QueryInterface instance that encapsulates the built
     * query in a format specific to the target system.
     *
     * @param PathInterface $path The path to the target column.
     * @param FilterInterface $filter The filter to apply on the target column.
     * @return QueryInterface The built query.
     */
    public function build(
        PathInterface $path,
        FilterInterface $filter
    ): QueryInterface;
}
