<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Operator;

use Derafu\Query\Operator\Contract\OperatorLoaderInterface;
use Derafu\Query\Operator\Contract\OperatorManagerFactoryInterface;
use Derafu\Query\Operator\Contract\OperatorManagerInterface;

/**
 * Creates an OperatorManager pre-loaded from a YAML operators file.
 */
final class OperatorManagerFactory implements OperatorManagerFactoryInterface
{
    public function __construct(
        private readonly OperatorLoaderInterface $loader
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function create(string $path): OperatorManagerInterface
    {
        return new OperatorManager($this->loader->loadFromFile($path));
    }
}
