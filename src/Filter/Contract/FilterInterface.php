<?php

declare(strict_types=1);

/**
 * Derafu: Query - Smart Query Builder.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Filter\Contract;

use Derafu\Query\Operator\Contract\OperatorInterface;
use RuntimeException;

interface FilterInterface
{
    /**
     * Gets the operator.
     *
     * @return OperatorInterface The operator.
     */
    public function getOperator(): OperatorInterface;

    /**
     * Gets the operator value.
     *
     * @return string|null The operator value or null if none.
     */
    public function getValue(): ?string;

    /**
     * Validates a parsed value against operator rules.
     *
     * @throws RuntimeException If any validation fails.
     */
    public function validate(): void;
}
