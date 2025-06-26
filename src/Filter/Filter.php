<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Filter;

use Derafu\Query\Filter\Contract\FilterInterface;
use Derafu\Query\Operator\Contract\OperatorInterface;
use InvalidArgumentException;

/**
 * Represents a parsed filter expression.
 *
 * Immutable value object that holds the components of a parsed filter
 * expression: the operator symbol and its value.
 */
final class Filter implements FilterInterface
{
    /**
     * Creates a new filter instance.
     *
     * @param OperatorInterface $operator The operator.
     * @param string|null $value The operator value if any.
     */
    public function __construct(
        private readonly OperatorInterface $operator,
        private readonly ?string $value = null,
    ) {
    }

    /**
     * Gets the operator.
     *
     * @return OperatorInterface The operator.
     */
    public function getOperator(): OperatorInterface
    {
        return $this->operator;
    }

    /**
     * Gets the operator value.
     *
     * @return string|null The operator value or null if none.
     */
    public function getValue(): ?string
    {
        return $this->value;
    }

    /**
     * Validates a parsed value against operator rules.
     *
     * @throws InvalidArgumentException If any validation fails.
     */
    public function validate(): void
    {
        $pattern = $this->operator->getValidationPattern();

        if ($pattern === null) {
            return;
        }

        $valid = (bool)preg_match($pattern, (string)$this->value);

        if (!$valid) {
            throw new InvalidArgumentException(sprintf(
                'Value "%s" is not valid for operator "%s".',
                $this->value,
                $this->operator->getSymbol()
            ));
        }
    }
}
