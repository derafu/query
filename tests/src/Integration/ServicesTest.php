<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsQuery\Integration;

use Derafu\Query\Filter\PathParser;
use Derafu\Query\Translation\QueryTranslationResourceProvider;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use Derafu\Translation\TranslatorFactory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

/**
 * Loads the real provider configuration and consumes its tagged resources.
 */
#[CoversNothing]
final class ServicesTest extends TestCase
{
    private function container(): ContainerBuilder
    {
        $root = dirname(__DIR__, 3);
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator($root . '/resources/config'));
        $loader->load('query-services.yaml');
        $this->assertArrayHasKey(
            QueryTranslationResourceProvider::class,
            $container->findTaggedServiceIds('derafu_translation.resource_provider')
        );
        $container->register('app.translator', Translator::class)
            ->setFactory([TranslatorFactory::class, 'create'])
            ->setArguments(['es', ['en'], new TaggedIteratorArgument('derafu_translation.resource_provider')]);
        foreach ($container->getDefinitions() as $definition) {
            $definition->setPublic(true);
        }
        $container->compile();

        return $container;
    }

    public function testConfigurationConstructsTheTranslationProvider(): void
    {
        $provider = $this->container()->get(QueryTranslationResourceProvider::class);
        $this->assertInstanceOf(QueryTranslationResourceProvider::class, $provider);
        $directories = [...$provider->getDirectories()];
        $this->assertCount(1, $directories);
        $this->assertSame(realpath(dirname(__DIR__, 3) . '/resources/translations'), realpath($directories[0]));
    }

    public function testTaggedResourcesTranslateARealDuplicateAliasException(): void
    {
        $translator = $this->container()->get('app.translator');
        $this->assertInstanceOf(Translator::class, $translator);
        try {
            (new PathParser())->parse('products[alias:p,alias:q]__name');
            $this->fail('A repeated alias option was accepted.');
        } catch (TranslatableInvalidArgumentException $exception) {
            $this->assertSame('La opción "alias" está definida más de una vez.', $exception->trans($translator));
        }
    }
}
