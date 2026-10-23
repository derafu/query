<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsQuery\Fixture;

use Closure;
use Derafu\Query\Builder\Sql\SqlSanitizerTrait;

/**
 * Exposes the SQL sanitizer methods for direct tests of the trait.
 */
final class SqlSanitizer
{
    use SqlSanitizerTrait;

    public function sanitize(string $expression): string
    {
        return $this->sanitizeSqlIdentifier($expression);
    }

    public function sanitizeSimple(string $expression): string
    {
        return $this->sanitizeSqlSimpleIdentifier($expression);
    }

    public function setSanitizeCallback(Closure $callback): void
    {
        $this->setSanitizeSqlIdentifierCallback($callback);
    }
}
