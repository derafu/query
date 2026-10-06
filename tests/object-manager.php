<?php

declare(strict_types=1);

/**
 * Derafu: Query - Query builder and filters library.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

use Derafu\TestsQuery\Integration\Bridge\DoctrineORM\ArrayCachePool;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\ORMSetup;

// Entity manager for PHPStan (phpstan-doctrine): it reads from it how the
// entities of the tests are mapped, so it knows that Doctrine sets their
// properties.
$config = ORMSetup::createConfig(false, null, new ArrayCachePool());
$config->setMetadataDriverImpl(new AttributeDriver([
    __DIR__ . '/src/Integration/Bridge/DoctrineORM/Entity',
]));
$config->enableNativeLazyObjects(true);

return new EntityManager(
    DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config),
    $config
);
