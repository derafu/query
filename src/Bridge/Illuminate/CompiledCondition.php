<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Bridge\Illuminate;

use Illuminate\Contracts\Database\Query\ConditionExpression;
use Illuminate\Database\Grammar;

/**
 * A condition compiled by the query SQL builder for Illuminate.
 *
 * @internal
 */
final readonly class CompiledCondition implements ConditionExpression
{
    public function __construct(private string $sql)
    {
    }

    public function getValue(Grammar $grammar): string
    {
        return $this->sql;
    }
}
