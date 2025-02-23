<?php

declare(strict_types=1);

/**
 * Derafu: Query - Smart Query Builder.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsQuery\Filter;

use Derafu\Query\Filter\Contract\PathInterface;
use Derafu\Query\Filter\Contract\PathParserInterface;
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
     * Instance of the parser being tested.
     */
    private PathParserInterface $parser;

    /**
     * Sets up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new PathParser();
    }

    /**
     * Data provider for parser tests.
     *
     * Provides test cases for both valid and invalid path expressions.
     *
     * @return array<string,array{string,string}> Test cases.
     */
    public static function parserCasesProvider(): array
    {
        $paths = require __DIR__ . '/../../fixtures/paths.php';

        $cases = [];

        // Process valid cases.
        foreach ($paths['OK'] as $expression) {
            $cases['Valid: ' . $expression] = [$expression, 'valid'];
        }

        // Process invalid cases.
        foreach ($paths['FAIL'] as $expression) {
            $cases['Invalid: ' . $expression] = [$expression, 'invalid'];
        }

        return $cases;
    }

    /**
     * Tests path parsing with various expressions.
     *
     * @param string $expression The path expression to test.
     * @param string $expectation Whether the case should be valid or invalid.
     */
    #[DataProvider('parserCasesProvider')]
    public function testPathParsing(
        string $expression,
        string $expectation
    ): void {
        if ($expectation === 'invalid') {
            $this->expectException(InvalidArgumentException::class);
            $this->parser->parse($expression);
        } else {
            $path = $this->parser->parse($expression);
            $this->assertInstanceOf(PathInterface::class, $path);
            $this->validatePath($path, $expression);
        }
    }

    /**
     * Tests specific path components with expected values.
     */
    public function testSpecificPathComponents(): void
    {
        // Test join types.
        $path = $this->parser->parse('author+left__books+inner__title');
        $segments = $path->getSegments();
        $this->assertSame('left', $segments[0]->getJoinType());
        $this->assertSame('inner', $segments[1]->getJoinType());
        $this->assertNull($segments[2]->getJoinType());

        // Test aliases.
        $path = $this->parser->parse('author:a__books:b__title');
        $segments = $path->getSegments();
        $this->assertSame('a', $segments[0]->getAlias());
        $this->assertSame('b', $segments[1]->getAlias());
        $this->assertNull($segments[2]->getAlias());

        // Test options.
        $path = $this->parser->parse('posts(order:created_at,limit:10)__comments');
        $segments = $path->getSegments();
        $this->assertSame(
            ['order' => 'created_at', 'limit' => '10'],
            $segments[0]->getOptions()
        );
        $this->assertNull($segments[1]->getOptions());

        // Test complex combination.
        $path = $this->parser->parse('author:a+left__books(order:title)');
        $segments = $path->getSegments();
        $this->assertSame('a', $segments[0]->getAlias());
        $this->assertSame('left', $segments[0]->getJoinType());
        $this->assertSame(['order' => 'title'], $segments[1]->getOptions());
    }

    /**
     * Validates a parsed path matches its original expression.
     */
    private function validatePath(PathInterface $path, string $expression): void
    {
        $segments = $path->getSegments();
        $parts = explode('__', $expression);

        $this->assertCount(count($parts), $segments);

        foreach ($segments as $index => $segment) {
            $this->assertNotEmpty($segment->getName());

            if ($segment->getJoinType() !== null) {
                $this->assertContains(
                    $segment->getJoinType(),
                    ['inner', 'left', 'cross', 'right']
                );
            }

            if ($segment->getOptions() !== null) {
                $this->assertIsArray($segment->getOptions());
                $this->assertNotEmpty($segment->getOptions());
            }

            if ($segment->getAlias() !== null) {
                $this->assertMatchesRegularExpression('/^\w+$/', $segment->getAlias());
            }
        }
    }
}
