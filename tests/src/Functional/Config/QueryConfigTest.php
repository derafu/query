<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsQuery\Config;

use Derafu\Query\Builder\Contract\QueryBuilderInterface;
use Derafu\Query\Config\JsonConfigLoader;
use Derafu\Query\Config\QueryConfig;
use Derafu\Query\Config\YamlConfigLoader;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QueryConfig::class)]
#[CoversClass(JsonConfigLoader::class)]
#[CoversClass(YamlConfigLoader::class)]
class QueryConfigTest extends TestCase
{
    public function testConstructWithValidConfig(): void
    {
        $config = new QueryConfig(['table' => 'customers']);
        $this->assertSame(['table' => 'customers'], $config->getConfig());
    }

    public function testConstructWithInvalidConfig(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new QueryConfig([]);
    }

    public function testFromYamlString(): void
    {
        $yaml = "table: customers\nwhere: status?=active";
        $config = QueryConfig::fromYamlString($yaml);

        $this->assertSame('customers', $config->getConfig()['table']);
        $this->assertSame('status?=active', $config->getConfig()['where']);
    }

    public function testFromJsonString(): void
    {
        $json = '{"table": "customers", "where": "status?=active"}';
        $config = QueryConfig::fromJsonString($json);

        $this->assertSame('customers', $config->getConfig()['table']);
        $this->assertSame('status?=active', $config->getConfig()['where']);
    }

    public function testApplyTo(): void
    {
        $configData = [
            'table' => 'customers',
            'alias' => 'c',
            'select' => 'c.id, c.name',
            'where' => 'status?=active',
            'orderBy' => ['name' => 'ASC'],
            'limit' => 10,
        ];

        $config = new QueryConfig($configData);

        // Create a mock for QueryBuilderInterface.
        $builder = $this->createMock(QueryBuilderInterface::class);

        // Set up the expected method calls
        $builder->expects($this->once())
            ->method('table')
            ->with('customers', 'c')
            ->willReturnSelf();

        $builder->expects($this->once())
            ->method('select')
            ->with('c.id, c.name')
            ->willReturnSelf();

        $builder->expects($this->once())
            ->method('where')
            ->with('status?=active')
            ->willReturnSelf();

        $builder->expects($this->once())
            ->method('orderBy')
            ->with(['name' => 'ASC'])
            ->willReturnSelf();

        $builder->expects($this->once())
            ->method('limit')
            ->with(10)
            ->willReturnSelf();

        // Apply configuration to the builder.
        $result = $config->applyTo($builder);

        // The result should be the builder itself.
        $this->assertSame($builder, $result);
    }

    public function testFromFile(): void
    {
        // Create temporary file.
        $yamlFile = tempnam(sys_get_temp_dir(), 'yaml_test_') . '.yaml';
        $yaml = "table: customers\nwhere: status?=active";
        file_put_contents($yamlFile, $yaml);

        $jsonFile = tempnam(sys_get_temp_dir(), 'json_test_') . '.json';
        $json = '{"table": "invoices", "where": "status?=paid"}';
        file_put_contents($jsonFile, $json);

        try {
            $yamlConfig = QueryConfig::fromFile($yamlFile);
            $this->assertSame('customers', $yamlConfig->getConfig()['table']);

            $jsonConfig = QueryConfig::fromFile($jsonFile);
            $this->assertSame('invoices', $jsonConfig->getConfig()['table']);

            // Test unsupported extension.
            $this->expectException(InvalidArgumentException::class);
            QueryConfig::fromFile('config.txt');
        } finally {
            // Clean up.
            @unlink($yamlFile);
            @unlink($jsonFile);
        }
    }
}
