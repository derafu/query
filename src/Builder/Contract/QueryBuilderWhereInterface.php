<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Builder\Contract;

use Derafu\Query\Filter\Contract\CompositeConditionInterface;
use Derafu\Query\Filter\Contract\ConditionInterface;

/**
 * Interface for query builders.
 *
 * This interface defines the contract for building queries from a condition
 * (simple or composite).
 *
 * In simple conditions the path represents the navigation through relations to
 * reach a target column, while the filter defines the conditions to apply on
 * that column. In composite conditions there is a list of other conditions to
 * apply (simple or composite).
 *
 * Query builders take these high-level representations and transform them into
 * executable queries in their respective target systems (SQL, ORM, etc.).
 *
 * @example
 * ```php
 * // Build a query to find books where the author's name is 'John'.
 * $path = new Path(['author', 'name']);
 * $filter = new Filter($equalsOperator, 'John');
 * $condition = new Condition($path, $filter);
 * $query = $builder->build($condition);
 * ```
 */
interface QueryBuilderWhereInterface
{
    /**
     * Builds a query from a condition (simple or composite).
     *
     * @param ConditionInterface|CompositeConditionInterface $condition
     * @return QueryInterface The built where section of the query.
     */
    public function build(
        ConditionInterface|CompositeConditionInterface $condition
    ): QueryInterface;
}
