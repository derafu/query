<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Bridge\ApiPlatform;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

/**
 * Custom Doctrine filter for API Platform that uses Derafu Query to apply
 * conditions to the query builder.
 *
 * @link https://api-platform.com/docs/guides/create-a-custom-doctrine-filter/
 */
final class SmartFilter implements FilterInterface
{
    public function apply(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void {
        $parameter = $context['parameter'] ?? null;
        $value = $parameter?->getValue();

        // If the value is missing or invalid, we skip the filter.
        if (!$value) {
            return;
        }

        // Determine which property to filter on. The QueryParameter attribute
        // provides the property name (explicitly or inferred).
        $property = $parameter->getProperty();
        if (!$property) {
            return;
        }

        // Generate a unique parameter name to avoid collisions in the DQL.
        $parameterName = $queryNameGenerator->generateParameterName($property);
        $alias = $queryBuilder->getRootAliases()[0];
        $queryBuilder
            ->andWhere(sprintf('LENGTH(%s.%s) >= :%s', $alias, $property, $parameterName))
            ->setParameter($parameterName, $value)
        ;
    }

    /**
     * {@inheritDoc}
     *
     * The getDescription method is no longer needed when using QueryParameter
     * because the documentation is handled by the attribute itself.
     */
    public function getDescription(string $resourceClass): array
    {
        return [];
    }
}
