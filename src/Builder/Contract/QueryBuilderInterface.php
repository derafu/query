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

use Derafu\Query\Filter\Contract\CompositeConditionInterface;
use Derafu\Query\Filter\Contract\ConditionInterface;

/**
 * Interface for query builders.
 *
 * This generic interface defines the common operations for building queries.
 * Implementations can adapt these operations to specific ORMs or query systems
 * (Doctrine, Laravel, PDO, etc).
 */
interface QueryBuilderInterface
{
    /**
     * Returns a clean instance of the Query Builder.
     *
     * This should be always the first method called to start the creation of
     * a query.
     *
     * @return self
     */
    public function new(): self;

    /**
     * Returns a clean instance of the Query Builder with a setted from table.
     *
     * @param string $table
     * @param string|null $alias
     * @return self
     */
    public function table(string $table, ?string $alias = null): self;

    /**
     * Creates a new SELECT query.
     *
     * @param string|array|null $columns Columns to select or null for all.
     * @return self For method chaining.
     */
    public function select(string|array|null $columns = null): self;

    /**
     * Sets the base table for the query.
     *
     * @param string $table The table name.
     * @param string|null $alias Optional alias for the table.
     * @return self For method chaining.
     */
    public function from(string $table, ?string $alias = null): self;

    /**
     * Adds a WHERE condition.
     *
     * Replaces any existing base conditions.
     *
     * @param string|array|ConditionInterface|CompositeConditionInterface $condition
     * The condition expression(s).
     * @return self For method chaining.
     */
    public function where(
        string|array|ConditionInterface|CompositeConditionInterface $condition
    ): self;

    /**
     * Adds an AND WHERE condition.
     *
     * @param string|array|ConditionInterface|CompositeConditionInterface $condition
     * The condition expression(s).
     * @return self For method chaining.
     */
    public function andWhere(
        string|array|ConditionInterface|CompositeConditionInterface $condition
    ): self;

    /**
     * Adds an OR WHERE condition.
     *
     * If condition is an array of conditions will generate an AND group of
     * conditions joines with OR to the existing WHERE conditions.
     *
     * @param string|array|ConditionInterface|CompositeConditionInterface $condition
     * The condition expression(s).
     * @return self For method chaining.
     */
    public function orWhere(
        string|array|ConditionInterface|CompositeConditionInterface $condition
    ): self;

    /**
     * Adds an OR group of conditions joined with AND to the existing WHERE
     * conditions.
     *
     * This method creates a group of conditions connected with OR operators,
     * then joins that entire group to the existing WHERE conditions using AND.
     * For example: "existing_condition AND (condition1 OR condition2 OR condition3)"
     *
     * @param array|ConditionInterface|CompositeConditionInterface $conditions
     * The conditions to add to the OR group.
     * @return self For method chaining.
     */
    public function andWhereOr(
        array|ConditionInterface|CompositeConditionInterface $conditions
    ): self;

    /**
     * Gets the raw query in implementation-specific format.
     *
     * @return mixed The raw query.
     */
    public function getQuery(): mixed;

    /**
     * Executes the query and returns the result.
     *
     * @return mixed The query result in implementation-specific format.
     */
    public function execute(): mixed;
}
