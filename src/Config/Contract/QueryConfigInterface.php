<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Config\Contract;

use Derafu\Query\Builder\Contract\QueryBuilderInterface;

/**
 * Interface for query configurations.
 *
 * Defines methods for working with query configurations and applying them to
 * query builders.
 */
interface QueryConfigInterface
{
    /**
     * Gets the configuration array.
     *
     * @return array<string, mixed> The configuration array.
     */
    public function getConfig(): array;

    /**
     * Applies this configuration to a query builder.
     *
     * @param QueryBuilderInterface $builder The query builder to configure.
     * @return QueryBuilderInterface The configured query builder.
     */
    public function applyTo(QueryBuilderInterface $builder): QueryBuilderInterface;
}
