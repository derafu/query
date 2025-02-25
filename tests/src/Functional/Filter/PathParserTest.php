<?php

declare(strict_types=1);

/**
 * Derafu: Query - Smart Query Builder.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsQuery\Functional\Filter;

use Derafu\Query\Filter\Path;
use Derafu\Query\Filter\PathParser;
use Derafu\Query\Filter\Segment;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the path parser.
 *
 * This test ensures the parser can correctly identify path segments and their
 * metadata from input strings, handling both valid and invalid cases.
 */
#[CoversClass(PathParser::class)]
#[CoversClass(Path::class)]
#[CoversClass(Segment::class)]
final class PathParserTest extends TestCase
{
    /**
     * Test parsing valid path expressions and comparing the results with
     * expected values.
     */
    #[DataProvider('validPathProvider')]
    public function testParseValidPath(string $expression, array $expected): void
    {
        $parser = new PathParser();
        $path = $parser->parse($expression);

        // Verify segment count.
        $this->assertCount(
            $expected['segments_count'],
            $path->getSegments(),
            "Path should have {$expected['segments_count']} segments"
        );

        // Verify segment names.
        $segments = $path->getSegments();
        foreach ($expected['segment_names'] as $index => $expectedName) {
            $this->assertSame(
                $expectedName,
                $segments[$index]->getName(),
                "Segment $index should have name '$expectedName'"
            );
        }

        // Verify segment options.
        foreach ($expected['segment_options'] as $index => $expectedOptions) {
            $this->assertSame(
                $expectedOptions,
                $segments[$index]->getOptions(),
                "Segment $index should have correct options"
            );
        }
    }

    /**
     * Test parsing invalid path expressions, which should throw exceptions.
     */
    #[DataProvider('invalidPathProvider')]
    public function testParseInvalidPath(string $expression): void
    {
        $parser = new PathParser();

        $this->expectException(InvalidArgumentException::class);
        $parser->parse($expression);
    }

    /**
     * Provides valid path expressions and their expected parsing results.
     */
    public static function validPathProvider(): array
    {
        $fixtures = require __DIR__ . '/../../../fixtures/functional/paths.php';
        $data = [];

        foreach ($fixtures['OK'] as $index => $fixture) {
            $data['Valid Path #' . $index . ':' . $fixture['expression']] = [
                $fixture['expression'],
                $fixture['expected'],
            ];
        }

        return $data;
    }

    /**
     * Provides invalid path expressions.
     */
    public static function invalidPathProvider(): array
    {
        $fixtures = require __DIR__ . '/../../../fixtures/functional/paths.php';
        $data = [];

        foreach ($fixtures['FAIL'] as $index => $fixture) {
            $data['Invalid Path #' . $index . ':' . $fixture['expression']] = [
                $fixture['expression'],
            ];
        }

        return $data;
    }

    /**
     * Test that the path converts back to a string correctly.
     */
    public function testPathToString(): void
    {
        $pathExpressions = [
            'author__books__title',
            'invoices[alias:i]__customers[on:customer_id=id]__name',
            'orders__items[on:order_id=id,on:branch_id=branch_id]__product',
        ];

        $parser = new PathParser();

        foreach ($pathExpressions as $expression) {
            $path = $parser->parse($expression);
            $regeneratedExpression = (string)$path;

            // Note: The exact string form might vary due to option ordering,
            // but re-parsing it should produce the same structure.
            $reparsedPath = $parser->parse($regeneratedExpression);

            // Compare the segment count.
            $this->assertCount(
                count($path->getSegments()),
                $reparsedPath->getSegments(),
                "Re-parsed path should have the same number of segments."
            );

            // Compare each segment.
            $segments = $path->getSegments();
            $reparsedSegments = $reparsedPath->getSegments();

            foreach ($segments as $index => $segment) {
                $this->assertSame(
                    $segment->getName(),
                    $reparsedSegments[$index]->getName(),
                    "Segment $index name should match after re-parsing."
                );

                $this->assertSame(
                    $segment->getOptions(),
                    $reparsedSegments[$index]->getOptions(),
                    "Segment $index options should match after re-parsing."
                );
            }
        }
    }
}
