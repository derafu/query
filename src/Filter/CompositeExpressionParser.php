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

use Derafu\Query\Filter\Contract\CompositeConditionInterface;
use Derafu\Query\Filter\Contract\CompositeExpressionParserInterface;
use Derafu\Query\Filter\Contract\ConditionInterface;
use Derafu\Query\Filter\Contract\ExpressionParserInterface;

/**
 * Recursive-descent parser for composite filter expressions.
 *
 * Grammar (|| binds looser than &&, matching standard boolean precedence):
 *
 *   expr → term  ( '||' term  )*
 *   term → atom  ( '&&' atom  )*
 *   atom → '(' expr ')' | leaf
 *   leaf → any characters until an unparenthesised && / || / )
 */
final class CompositeExpressionParser implements CompositeExpressionParserInterface
{
    public function __construct(
        private readonly ExpressionParserInterface $expressionParser
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function parse(string $expression): ConditionInterface|CompositeConditionInterface
    {
        return $this->parseExpr(trim($expression));
    }

    /**
     * expr → term ('||' term)*
     */
    private function parseExpr(string $s): ConditionInterface|CompositeConditionInterface
    {
        $parts = $this->splitOn($s, '||');

        if (count($parts) === 1) {
            return $this->parseTerm($parts[0]);
        }

        $or = CompositeCondition::or();
        foreach ($parts as $part) {
            $or->add($this->parseTerm(trim($part)));
        }

        return $or;
    }

    /**
     * term → atom ('&&' atom)*
     */
    private function parseTerm(string $s): ConditionInterface|CompositeConditionInterface
    {
        $parts = $this->splitOn($s, '&&');

        if (count($parts) === 1) {
            return $this->parseAtom($parts[0]);
        }

        $and = CompositeCondition::and();
        foreach ($parts as $part) {
            $and->add($this->parseAtom(trim($part)));
        }

        return $and;
    }

    /**
     * atom → '(' expr ')' | leaf
     */
    private function parseAtom(string $s): ConditionInterface|CompositeConditionInterface
    {
        if (
            str_starts_with($s, '(')
            && $this->findMatchingClose($s, 0) === strlen($s) - 1
        ) {
            return $this->parseExpr(substr($s, 1, -1));
        }

        return $this->expressionParser->parse($s);
    }

    /**
     * Splits $s on $delim at paren depth 0 only.
     *
     * Paren depth is tracked so that aggregate function calls like SUM(amount)
     * and explicit grouping parentheses in sub-expressions are not treated as
     * split points.
     *
     * @return list<string>
     */
    private function splitOn(string $s, string $delim): array
    {
        $parts = [];
        $depth = 0;
        $len   = strlen($s);
        $dlen  = strlen($delim);
        $start = 0;

        for ($i = 0; $i < $len; $i++) {
            if ($s[$i] === '(') {
                $depth++;
            } elseif ($s[$i] === ')') {
                $depth--;
            } elseif ($depth === 0 && substr($s, $i, $dlen) === $delim) {
                $parts[] = substr($s, $start, $i - $start);
                $start   = $i + $dlen;
                $i      += $dlen - 1;
            }
        }

        $parts[] = substr($s, $start);

        return $parts;
    }

    /**
     * Returns the index of the `)` that matches the `(` at $openPos, or -1.
     */
    private function findMatchingClose(string $s, int $openPos): int
    {
        $depth = 0;
        $len   = strlen($s);

        for ($i = $openPos; $i < $len; $i++) {
            if ($s[$i] === '(') {
                $depth++;
            } elseif ($s[$i] === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return -1;
    }
}
