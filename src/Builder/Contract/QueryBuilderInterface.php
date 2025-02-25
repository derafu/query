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
     * @param string|array $columns Columns to select or null for all.
     * @return self For method chaining.
     */
    public function select(string|array $columns): self;

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
     * Sets the result limit for the query.
     *
     * @param int $limit Maximum number of records to return.
     * @return self For method chaining.
     */
    public function limit(int $limit): self;

    /**
     * Sets the result offset for the query.
     *
     * @param int $offset Number of records to skip.
     * @return self For method chaining.
     */
    public function offset(int $offset): self;

    /**
     * Sets the order by clause for the query.
     *
     * @param string|array $columns Column(s) to order by.
     * @param string $direction Direction (ASC or DESC). Only used when $columns is a string.
     * @return self For method chaining.
     */
    public function orderBy(string|array $columns, string $direction = 'ASC'): self;

    /**
     * Adds a group by clause to the query.
     *
     * @param string|array $columns Column(s) to group by.
     * @return self For method chaining.
     */
    public function groupBy(string|array $columns): self;

    /**
     * Adds a HAVING clause to filter grouped results.
     *
     * @param string|array|ConditionInterface|CompositeConditionInterface $condition The condition for HAVING.
     * @return self For method chaining.
     */
    public function having(
        string|array|ConditionInterface|CompositeConditionInterface $condition
    ): self;

    /**
     * Performs a DISTINCT selection.
     *
     * @param bool $distinct Whether to add DISTINCT to the query.
     * @return self For method chaining.
     */
    public function distinct(bool $distinct = true): self;

    /**
     * Joins another table to the query.
     *
     * IMPORTANT: The condition is not sanitized, it must be safe!.
     *
     * @param string $table The table to join.
     * @param string $condition The join condition.
     * @param string $type The join type (INNER, LEFT, RIGHT, etc).
     * @param string|null $alias Optional alias for the joined table.
     * @return self For method chaining.
     */
    public function join(
        string $table,
        string $condition,
        string $type = 'INNER',
        ?string $alias = null
    ): self;

    /**
     * Adds a LEFT JOIN to the query.
     *
     * IMPORTANT: The condition is not sanitized, it must be safe!.
     *
     * @param string $table The table to join.
     * @param string $condition The join condition.
     * @param string|null $alias Optional alias for the joined table.
     * @return self For method chaining.
     */
    public function leftJoin(string $table, string $condition, ?string $alias = null): self;

    /**
     * Adds a RIGHT JOIN to the query.
     *
     * IMPORTANT: The condition is not sanitized, it must be safe!.
     *
     * @param string $table The table to join.
     * @param string $condition The join condition.
     * @param string|null $alias Optional alias for the joined table.
     * @return self For method chaining.
     */
    public function rightJoin(string $table, string $condition, ?string $alias = null): self;

    /**
     * Adds an INNER JOIN to the query.
     *
     * IMPORTANT: The condition is not sanitized, it must be safe!.
     *
     * @param string $table The table to join.
     * @param string $condition The join condition.
     * @param string|null $alias Optional alias for the joined table.
     * @return self For method chaining.
     */
    public function innerJoin(string $table, string $condition, ?string $alias = null): self;

    /**
     * Adds a CROSS JOIN to the query.
     *
     * @param string $table The table to join.
     * @param string|null $alias Optional alias for the joined table.
     * @return self For method chaining.
     */
    public function crossJoin(string $table, ?string $alias = null): self;

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
