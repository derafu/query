<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Builder;

use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;

/**
 * Allocates aliases in the namespace selected by the compiler. SQL subqueries
 * can use local scopes; Doctrine DQL shares an allocation namespace across the
 * complete query while validating explicit aliases in their declaration scope.
 *
 * @internal
 */
final class AliasScope
{
    /** @var array<string, true> */
    private array $reserved = [];

    private int $next = 0;

    /** @param iterable<string> $outerAliases Aliases visible to the correlation. */
    public function __construct(iterable $outerAliases = [])
    {
        foreach ($outerAliases as $alias) {
            if ($alias !== '') {
                $this->reserved[strtolower($alias)] = true;
            }
        }
    }

    /** Reserves explicit aliases before any automatic aliases are allocated. */
    public function reserve(string $alias): void
    {
        $key = strtolower($alias);
        if (isset($this->reserved[$key])) {
            throw new InvalidArgumentException([
                'Alias "{alias}" is already used in this query scope.',
                'alias' => $alias,
            ]);
        }
        $this->reserved[$key] = true;
    }

    public function generate(): string
    {
        do {
            $alias = '_____derafu_' . $this->next++;
        } while (isset($this->reserved[$alias]));
        $this->reserved[$alias] = true;

        return $alias;
    }
}
