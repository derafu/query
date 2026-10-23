<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Bridge\Illuminate;

use Derafu\Translation\Exception\Core\TranslatableRuntimeException as RuntimeException;
use Illuminate\Contracts\Database\Query\ConditionExpression;
use Illuminate\Database\Grammar;

/**
 * A condition compiled by the query SQL builder for Illuminate.
 *
 * @internal
 */
final readonly class CompiledCondition implements ConditionExpression
{
    private string $sql;

    /** @var list<mixed> */
    private array $bindings;

    /**
     * @param array<string, mixed> $parameters Named parameters from the SQL compiler.
     */
    public function __construct(string $sql, array $parameters = [])
    {
        $bindings = [];
        // Ignore SQL literals, quoted identifiers and comments. PostgreSQL casts
        // (::type) are not parameter markers. Each occurrence gets its own binding,
        // including repeated parameters and templates that reorder their operands.
        $pattern = <<<'REGEX'
~'(?:''|\\.|[^'\\])*'(*SKIP)(*F)
|"(?:""|\\.|[^"\\])*"(*SKIP)(*F)
|`(?:``|\\.|[^`\\])*`(*SKIP)(*F)
|\$(?:[A-Za-z_][A-Za-z0-9_]*)?\$.*?\$(?:[A-Za-z_][A-Za-z0-9_]*)?\$(*SKIP)(*F)
|--[^\r\n]*(*SKIP)(*F)
|\#[^\r\n]*(*SKIP)(*F)
|/\*.*?\*/(*SKIP)(*F)
|(?<!:):([A-Za-z_][A-Za-z0-9_]*)~sx
REGEX;
        $compiled = preg_replace_callback(
            $pattern,
            static function (array $match) use ($parameters, &$bindings): string {
                $name = $match[1];
                if (!array_key_exists($name, $parameters)) {
                    return $match[0];
                }
                $bindings[] = $parameters[$name];
                return '?';
            },
            $sql
        );
        if ($compiled === null) {
            throw new RuntimeException([
                'Failed to compile Illuminate parameter placeholders: {error}',
                'error' => preg_last_error_msg(),
            ]);
        }
        $this->sql = $compiled;
        $this->bindings = $bindings;
    }

    /** @return list<mixed> */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    public function getValue(Grammar $grammar): string
    {
        return $this->sql;
    }
}
