<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Bridge\Contract;

use Derafu\Query\Filter\Contract\CompositeConditionInterface;
use Derafu\Query\Filter\Contract\ConditionInterface;

/**
 * Interface for query builder condition appliers.
 *
 * This interface defines the contract for applying conditions to query builders.
 * Implementations can adapt this interface to specific ORMs or query systems
 * (Doctrine, Laravel, PDO, etc).
 */
interface QueryBuilderConditionApplierInterface
{
    /**
     * Applies a condition as a WHERE clause.
     *
     * Implementations should also handle FROM inference and JOIN application
     * when the condition contains multi-segment path expressions.
     *
     * @param object $queryBuilder The query builder to apply the condition to.
     */
    public function apply(
        object $queryBuilder,
        ConditionInterface|CompositeConditionInterface $condition
    ): void;

    /**
     * Applies a condition as a HAVING clause.
     *
     * Does not perform FROM inference or JOIN application — those are expected
     * to have been applied already via apply().
     *
     * @param object $queryBuilder The query builder to apply the condition to.
     */
    public function applyHaving(
        object $queryBuilder,
        ConditionInterface|CompositeConditionInterface $condition
    ): void;
}
