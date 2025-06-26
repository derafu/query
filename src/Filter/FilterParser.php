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
use Derafu\Query\Filter\Contract\FilterParserInterface;
use Derafu\Query\Operator\Contract\OperatorManagerInterface;
use InvalidArgumentException;

/**
 * Parses operator expressions to extract operator and value components.
 *
 * This class handles the parsing of operator expressions into their constituent
 * parts, identifying the operator symbol and extracting any associated value.
 */
final class FilterParser implements FilterParserInterface
{
    /**
     * Creates a new parser instance.
     *
     * @param OperatorManagerInterface $manager The operator manager to use.
     */
    public function __construct(
        private readonly OperatorManagerInterface $manager
    ) {
    }

    /**
     * Extracts operator and value from an expression string.
     *
     * @param string $expression The input expression to parse.
     * @return FilterInterface Extracted filter.
     * @throws InvalidArgumentException If expression format is invalid.
     */
    public function parse(string $expression): FilterInterface
    {
        // Try to match standard operators first.
        $operators = $this->manager->getOperatorsSortedByLength();
        foreach ($operators as $operator) {
            if (str_starts_with($expression, $operator)) {
                $value = substr($expression, strlen($operator));
                $filter = new Filter(
                    operator: $this->manager->getOperator($operator),
                    value: $value !== '' ? $value : null
                );
                $filter->validate();
                return $filter;
            }
        }

        throw new InvalidArgumentException(
            sprintf('No valid operator found in expression: %s', $expression)
        );
    }
}
