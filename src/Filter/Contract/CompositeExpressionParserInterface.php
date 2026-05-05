<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Filter\Contract;

/**
 * Interface for the composite expression parser.
 *
 * Parses a composite expression string containing `&&` (AND) and `||` (OR)
 * operators with optional grouping via `()` into a tree of conditions.
 */
interface CompositeExpressionParserInterface
{
    /**
     * Parses a composite expression string into a condition tree.
     *
     * A single leaf (no `&&` or `||` at top level) returns a plain
     * `ConditionInterface`. Two or more leaves joined by `&&` or `||`
     * return a `CompositeConditionInterface` with AND/OR type respectively.
     * Parentheses control precedence; `&&` binds tighter than `||`.
     *
     * @param string $expression
     * @return ConditionInterface|CompositeConditionInterface
     */
    public function parse(string $expression): ConditionInterface|CompositeConditionInterface;
}
