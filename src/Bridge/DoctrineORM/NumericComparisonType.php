<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Bridge\DoctrineORM;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\DecimalType;

/**
 * Binds numeric comparison operands without converting them to PHP floats.
 *
 * SQLite expressions have no column affinity, so a string parameter must be
 * explicitly converted to NUMERIC. PostgreSQL also needs this cast to accept
 * decimal thresholds when the aggregate returns an integer. SQLite's own numeric
 * precision limits still apply; the original decimal representation reaches the
 * database intact.
 *
 * @internal
 */
final class NumericComparisonType extends DecimalType
{
    public function convertToDatabaseValueSQL(string $sqlExpr, AbstractPlatform $platform): string
    {
        return $platform instanceof SQLitePlatform || $platform instanceof PostgreSQLPlatform
            ? 'CAST(' . $sqlExpr . ' AS NUMERIC)'
            : $sqlExpr;
    }
}
