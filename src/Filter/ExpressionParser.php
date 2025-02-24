<?php

declare(strict_types=1);

/**
 * Derafu: Query - Smart Query Builder.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Filter;

use Derafu\Query\Filter\Contract\ConditionInterface;
use Derafu\Query\Filter\Contract\ExpressionParserInterface;
use Derafu\Query\Filter\Contract\FilterParserInterface;
use Derafu\Query\Filter\Contract\PathParserInterface;

/**
 * It translates string filter expressions into conditions using the path and
 * filter parsers.
 */
class ExpressionParser implements ExpressionParserInterface
{
    /**
     * Creates a new expression parser.
     *
     * @param PathParserInterface $pathParser
     * @param FilterParserInterface $filterParser
     */
    public function __construct(
        private readonly PathParserInterface $pathParser,
        private readonly FilterParserInterface $filterParser
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function parse(string $expression): ConditionInterface
    {
        [$pathExpression, $filterExpression] = explode('?', $expression, 2);

        $path = $this->pathParser->parse($pathExpression);
        $filter = $this->filterParser->parse($filterExpression);

        return new Condition($path, $filter);
    }
}
