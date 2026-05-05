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

use Derafu\Query\Filter\CompositeCondition;
use Derafu\Query\Filter\CompositeExpressionParser;
use Derafu\Query\Filter\Condition;
use Derafu\Query\Filter\Contract\CompositeConditionInterface;
use Derafu\Query\Filter\Contract\ConditionInterface;
use Derafu\Query\Filter\ExpressionParser;
use Derafu\Query\Filter\Filter;
use Derafu\Query\Filter\FilterParser;
use Derafu\Query\Filter\Path;
use Derafu\Query\Filter\PathParser;
use Derafu\Query\Filter\Segment;
use Derafu\Query\Operator\Operator;
use Derafu\Query\Operator\OperatorLoader;
use Derafu\Query\Operator\OperatorManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CompositeExpressionParser::class)]
#[CoversClass(CompositeCondition::class)]
#[UsesClass(Condition::class)]
#[UsesClass(ExpressionParser::class)]
#[UsesClass(Filter::class)]
#[UsesClass(FilterParser::class)]
#[UsesClass(Path::class)]
#[UsesClass(PathParser::class)]
#[UsesClass(Segment::class)]
#[UsesClass(Operator::class)]
#[UsesClass(OperatorLoader::class)]
#[UsesClass(OperatorManager::class)]
final class CompositeExpressionParserTest extends TestCase
{
    private CompositeExpressionParser $parser;

    protected function setUp(): void
    {
        $pathParser = new PathParser();
        $loader = new OperatorLoader();
        $operators = $loader->loadFromFile(
            __DIR__ . '/../../../../resources/operators.yaml'
        );
        $manager = new OperatorManager($operators);
        $filterParser = new FilterParser($manager);
        $expressionParser = new ExpressionParser($pathParser, $filterParser);

        $this->parser = new CompositeExpressionParser($expressionParser);
    }

    public static function structureProvider(): array
    {
        return [
            // Single leaf → plain ConditionInterface (no composite wrapper).
            'single_leaf' => [
                'category?=software',
                'condition',
                null,
                null,
            ],

            // Simple AND: two leaves joined by &&.
            'simple_and' => [
                'category?=software&&price?>200',
                'AND',
                2,
                null,
            ],

            // Simple OR: two leaves joined by ||.
            'simple_or' => [
                'category?=electronics||category?=hardware',
                'OR',
                2,
                null,
            ],

            // Three-way AND.
            'three_way_and' => [
                'status?=active&&type?=person&&tax_id?^12',
                'AND',
                3,
                null,
            ],

            // Three-way OR.
            'three_way_or' => [
                'status?=paid||status?=issued||status?=draft',
                'OR',
                3,
                null,
            ],

            // AND with nested OR: status?=active&&(type?=person||tax_id?^78).
            // Root: AND[condition, OR[condition, condition]]
            'and_with_nested_or' => [
                'status?=active&&(type?=person||tax_id?^78)',
                'AND',
                2,
                [
                    ['type' => 'condition'],
                    ['type' => 'OR', 'count' => 2],
                ],
            ],

            // OR with nested AND: category?=software||(category?=hardware&&price > 200).
            // Root: OR[condition, AND[condition, condition]]
            'or_with_nested_and' => [
                'category?=software||(category?=hardware&&price?>200)',
                'OR',
                2,
                [
                    ['type' => 'condition'],
                    ['type' => 'AND', 'count' => 2],
                ],
            ],

            // Double nesting: a&&(b||(c&&d)).
            'double_nested' => [
                'status?=active&&(type?=person||(tax_id?^78&&deleted_at?is:null))',
                'AND',
                2,
                [
                    ['type' => 'condition'],
                    ['type' => 'OR', 'count' => 2],
                ],
            ],
        ];
    }

    #[DataProvider('structureProvider')]
    public function testParsedStructure(
        string $expression,
        string $rootType,
        ?int $childCount,
        ?array $childSpecs
    ): void {
        $result = $this->parser->parse($expression);

        if ($rootType === 'condition') {
            $this->assertInstanceOf(ConditionInterface::class, $result);
            $this->assertNotInstanceOf(CompositeConditionInterface::class, $result);
            return;
        }

        $this->assertInstanceOf(CompositeConditionInterface::class, $result);
        $this->assertSame($rootType, $result->getType());

        if ($childCount !== null) {
            $this->assertCount($childCount, $result->getConditions());
        }

        if ($childSpecs !== null) {
            $children = $result->getConditions();
            foreach ($childSpecs as $i => $spec) {
                $child = $children[$i];
                if ($spec['type'] === 'condition') {
                    $this->assertInstanceOf(ConditionInterface::class, $child);
                    $this->assertNotInstanceOf(CompositeConditionInterface::class, $child);
                } else {
                    $this->assertInstanceOf(CompositeConditionInterface::class, $child);
                    $this->assertSame($spec['type'], $child->getType());
                    if (isset($spec['count'])) {
                        $this->assertCount($spec['count'], $child->getConditions());
                    }
                }
            }
        }
    }

    public function testAndBindsTighterThanOr(): void
    {
        // a||b&&c should parse as a||(b&&c), not (a||b)&&c.
        $result = $this->parser->parse('status?=paid||status?=issued&&total?>1000');

        $this->assertInstanceOf(CompositeConditionInterface::class, $result);
        $this->assertSame('OR', $result->getType());

        $children = $result->getConditions();
        $this->assertCount(2, $children);

        // First child: plain condition (status?=paid).
        $this->assertInstanceOf(ConditionInterface::class, $children[0]);
        $this->assertNotInstanceOf(CompositeConditionInterface::class, $children[0]);

        // Second child: AND[status?=issued, total > 1000].
        $this->assertInstanceOf(CompositeConditionInterface::class, $children[1]);
        $this->assertSame('AND', $children[1]->getType());
        $this->assertCount(2, $children[1]->getConditions());
    }
}
