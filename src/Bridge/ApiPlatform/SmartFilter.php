<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Bridge\ApiPlatform;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Derafu\Query\Bridge\DoctrineORMQueryBuilderConditionApplier;
use Derafu\Query\Bridge\Exception\UnsupportedOperatorException;
use Derafu\Query\Filter\Contract\CompositeExpressionParserInterface;
use Doctrine\ORM\QueryBuilder;
use Throwable;

/**
 * API Platform filter that accepts Derafu Query expressions as parameter values.
 *
 * Register it on a resource property via QueryParameter. The URL value is the
 * operator + operand part of a Derafu expression; the property name provides
 * the path segment.
 *
 * Example:
 *   #[QueryParameter(key: 'total', property: 'total', filter: SmartFilter::class)]
 *   #[QueryParameter(key: 'status', property: 'status', filter: SmartFilter::class)]
 *
 *   GET /api/products?price=>1000&status=in:paid,issued
 *
 * For Symfony DI, inject CompositeExpressionParserInterface as a service.
 * For zero-config usage, call SmartFilter::create().
 *
 * @link https://api-platform.com/docs/guides/create-a-custom-doctrine-filter/
 */
final class SmartFilter implements FilterInterface
{
    public function __construct(
        private readonly CompositeExpressionParserInterface $compositeParser,
        private readonly DoctrineORMQueryBuilderConditionApplier $applier,
        private readonly string $smartFilterProperty = '__derafu_smart_filter',
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * Builds a Derafu expression from the parameter property and value, parses
     * it, and applies the resulting condition to the QueryBuilder.
     *
     * Invalid expressions and DQL-incompatible operators are skipped silently
     * so that one bad filter does not crash the entire collection endpoint.
     */
    public function apply(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void {
        $parameter = $context['parameter'] ?? null;
        $property = $parameter?->getProperty();
        $value = $parameter?->getValue();

        if (
            !is_string($property)
            || $property === ''
            || !is_string($value)
            || $value === ''
        ) {
            return;
        }

        if ($property === $this->smartFilterProperty) {
            $expression = $value;
        } else {
            $expression = $property . '?' . $value;
        }

        try {
            $condition = $this->compositeParser->parse($expression);
            $this->applier->apply($queryBuilder, $condition);
        } catch (UnsupportedOperatorException) {
            // Operators requiring SQL functions (date:, b&, ilike:, …) are
            // not expressible in DQL — skip silently.
        } catch (Throwable) {
            // Malformed expressions are skipped silently.
        }
    }

    /**
     * {@inheritDoc}
     *
     * Documentation is handled by the QueryParameter attribute itself.
     */
    public function getDescription(string $resourceClass): array
    {
        return [];
    }
}
