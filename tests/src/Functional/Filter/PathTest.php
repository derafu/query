<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsQuery\Functional\Filter;

use Derafu\Query\Filter\Path;
use Derafu\Query\Filter\Segment;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Path::class)]
#[UsesClass(Segment::class)]
final class PathTest extends TestCase
{
    public function testEmptyPathsAreRejected(): void
    {
        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage('Path must have at least one segment.');
        new Path([]);
    }

    public function testSingleSegmentIsBothTheFirstAndTheLast(): void
    {
        $segment = new Segment('id');
        $path = new Path([$segment]);
        $this->assertSame($segment, $path->getFirstSegment());
        $this->assertSame($segment, $path->getLastSegment());
        $this->assertSame('id', (string) $path);
    }

    public function testLastSegmentUsesInsertionOrderAndPreservesKeys(): void
    {
        $first = new Segment('customers');
        $middle = new Segment('invoices');
        $last = new Segment('total');
        $segments = [0 => $first, 9 => $middle, 3 => $last];
        $path = new Path($segments);

        $this->assertSame($first, $path->getFirstSegment());
        $this->assertSame($last, $path->getLastSegment());
        $this->assertSame($segments, $path->getSegments());
        $this->assertSame('customers__invoices__total', (string) $path);
    }
}
