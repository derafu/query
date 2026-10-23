<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsQuery\Functional\Filter;

use Derafu\Query\Filter\Path;
use Derafu\Query\Filter\PathParser;
use Derafu\Query\Filter\Segment;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
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
     *
     * @param array{segments_count:int,segment_names:list<string>,segment_options:list<array<string,string|array<string,string>>>} $expected
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
     *
     * @return array<string,array{string,array{segments_count:int,segment_names:list<string>,segment_options:list<array<string,string|array<string,string>>>}}>
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
     *
     * @return array<string,array{string}>
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

    /**
     * @return array<string,array{string,string}>
     */
    public static function repeatedOptions(): array
    {
        return [
            'different aliases' => [
                'items[alias:a,alias:b]__name',
                'Option "alias" is defined more than once.',
            ],
            'identical aliases' => [
                'items[alias:a,alias:a]__name',
                'Option "alias" is defined more than once.',
            ],
            'different join types' => [
                'items[join:left,join:inner]__name',
                'Option "join" is defined more than once.',
            ],
            'identical join types' => [
                'items[join:left,join:left]__name',
                'Option "join" is defined more than once.',
            ],
            'non-adjacent duplicate' => [
                'items[alias:a,on:id=item_id,alias:b]__name',
                'Option "alias" is defined more than once.',
            ],
            'different targets for one source column' => [
                'items[on:id=item_id,on:id=other_id]__name',
                'Column "id" is defined more than once in option "on".',
            ],
            'identical column correspondence' => [
                'items[on:id=item_id,on:id=item_id]__name',
                'Column "id" is defined more than once in option "on".',
            ],
            'scalar followed by mapping' => [
                'items[alias:a,alias:id=other]__name',
                'Option "alias" cannot mix scalar and mapping values.',
            ],
            'mapping followed by scalar' => [
                'items[alias:id=other,alias:a]__name',
                'Option "alias" cannot mix scalar and mapping values.',
            ],
            'scalar on followed by mapping' => [
                'items[on:invalid,on:id=item_id]__name',
                'Option "on" cannot mix scalar and mapping values.',
            ],
            'mapping on followed by scalar' => [
                'items[on:id=item_id,on:invalid]__name',
                'Option "on" cannot mix scalar and mapping values.',
            ],
            'duplicate in a later segment' => [
                'orders__items[alias:a,alias:b]__name',
                'Option "alias" is defined more than once.',
            ],
            'duplicate in an exists path' => [
                '___items[on:id=item_id,on:id=other_id]__name',
                'Column "id" is defined more than once in option "on".',
            ],
            'duplicate in a nested exists path' => [
                '___orders___items[join:left,join:inner]__name',
                'Option "join" is defined more than once.',
            ],
            'duplicate custom scalar option' => [
                'items[custom:a,custom:b]__name',
                'Option "custom" is defined more than once.',
            ],
            'duplicate custom mapping column' => [
                'items[custom:id=a,custom:id=b]__name',
                'Column "id" is defined more than once in option "custom".',
            ],
        ];
    }

    #[DataProvider('repeatedOptions')]
    public function testRepeatedAssignmentsAreConfigurationErrors(string $expression, string $message): void
    {
        $this->expectException(TranslatableInvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        (new PathParser())->parse($expression);
    }

    public function testOptionsAreIndependentAcrossSegmentsAndKeepAllCorrespondences(): void
    {
        $expression = 'orders[alias:o,join:left]__items[alias:i,join:left,on:id=order_id,on:branch_id=branch_id]__name';
        $parser = new PathParser();
        $path = $parser->parse($expression);
        $segments = $path->getSegments();

        $this->assertSame(['alias' => 'o', 'join' => 'left'], $segments[0]->getOptions());
        $this->assertSame([
            'alias' => 'i',
            'join' => 'left',
            'on' => ['id' => 'order_id', 'branch_id' => 'branch_id'],
        ], $segments[1]->getOptions());
        $this->assertSame($expression, (string) $path);
        $this->assertSame($segments[1]->getOptions(), $parser->parse((string) $path)->getSegments()[1]->getOptions());
    }

    public function testOptionsDoNotBypassInvalidSegmentName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field name cannot be empty.');

        (new PathParser())->parse('0[alias:a]');
    }
}
