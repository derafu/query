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

use InvalidArgumentException;

/**
 * Service for parsing path expressions into Path objects.
 */
interface PathParserInterface
{
    /**
     * Parses a path expression into a Path object.
     *
     * @param string $expression The path expression to parse.
     * @return PathInterface The parsed path.
     * @throws InvalidArgumentException If the expression is invalid.
     */
    public function parse(string $expression): PathInterface;
}
