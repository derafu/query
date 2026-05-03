<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Bridge\Exception;

use RuntimeException;

/**
 * Thrown when an operator is used in a context that does not support it.
 *
 * Doctrine ORM's DQL does not support SQL-only constructs such as date
 * functions (DATE, MONTH, YEAR), bitwise operators, or regular expressions.
 * This exception is raised when such operators appear in a condition tree
 * passed to DoctrineORMQueryBuilderConditionApplier.
 */
final class UnsupportedOperatorException extends RuntimeException
{
    public static function forOperator(string $symbol, string $reason = ''): self
    {
        return new self(sprintf(
            'Operator "%s" is not supported in DQL context%s.',
            $symbol,
            $reason !== '' ? ': ' . $reason : ''
        ));
    }
}
