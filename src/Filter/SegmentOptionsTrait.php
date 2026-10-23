<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Filter;

use Derafu\Query\Filter\Contract\SegmentInterface;
use Derafu\Support\ArrayReader;
use Derafu\Support\ArrayType;

/**
 * Reads segment options at their point of use, leaving unknown options intact.
 *
 * @internal
 */
trait SegmentOptionsTrait
{
    private function readSegmentOptions(SegmentInterface $segment): ArrayReader
    {
        return new ArrayReader($segment->getOptions(), $segment->getName());
    }

    /**
     * @return array<string,string>|null
     */
    private function readSegmentJoinConditions(SegmentInterface $segment): ?array
    {
        return $this->readSegmentOptions($segment)->get(
            'on',
            new ArrayType('array<string,string>', self::parseSegmentJoinConditions(...))
        );
    }

    /**
     * Column identifiers must be strings on both sides of each correspondence.
     *
     * @return array<string,string>
     */
    private static function parseSegmentJoinConditions(mixed $value, string $path): array
    {
        $conditions = ArrayType::array()->parse($value, $path);
        $result = [];
        foreach ($conditions as $source => $target) {
            $columnPath = ArrayType::path($path, $source);
            $source = ArrayType::string()->parse($source, $columnPath);
            $result[$source] = ArrayType::string()->parse($target, $columnPath);
        }

        return $result;
    }
}
