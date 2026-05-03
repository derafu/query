<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Operator\Contract;

/**
 * Creates an OperatorManager pre-loaded from an operators file.
 */
interface OperatorManagerFactoryInterface
{
    /**
     * Creates an OperatorManager pre-loaded from an operators file.
     *
     * @param string $path Path to the operators file.
     * @return OperatorManagerInterface The operator manager.
     */
    public function create(string $path): OperatorManagerInterface;
}
