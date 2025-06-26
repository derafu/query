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
 * Interface for the expression parsers.
 */
interface ExpressionParserInterface
{
    /**
     * Translates string filter expressions into conditions using the path and
     * filter parsers.
     *
     * @param string $expression
     * @return ConditionInterface
     */
    public function parse(string $expression): ConditionInterface;
}
