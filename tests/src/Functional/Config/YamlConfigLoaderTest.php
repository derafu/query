<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsQuery\Config;

use Derafu\Query\Config\Contract\ConfigLoaderInterface;
use Derafu\Query\Config\YamlConfigLoader;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(YamlConfigLoader::class)]
class YamlConfigLoaderTest extends TestCase
{
    private ConfigLoaderInterface $loader;

    protected function setUp(): void
    {
        $this->loader = new YamlConfigLoader();
    }

    public function testLoadFromString(): void
    {
        $yaml = <<<YAML
table: customers
select: '*'
where: status?=active
YAML;

        $config = $this->loader->loadFromString($yaml);

        $this->assertIsArray($config);
        $this->assertSame('customers', $config['table']);
        $this->assertSame('*', $config['select']);
        $this->assertSame('status?=active', $config['where']);
    }

    public function testLoadFromStringInvalidYaml(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->loader->loadFromString('table: customers: invalid');
    }

    public function testLoadFromFile(): void
    {
        // Create temporary file.
        $file = tempnam(sys_get_temp_dir(), 'yaml_test_');
        $yaml = <<<YAML
table: invoices
select: 'id, number'
where: status?=paid
YAML;
        file_put_contents($file, $yaml);

        try {
            $config = $this->loader->loadFromFile($file);

            $this->assertIsArray($config);
            $this->assertSame('invoices', $config['table']);
            $this->assertSame('id, number', $config['select']);
            $this->assertSame('status?=paid', $config['where']);
        } finally {
            // Clean up.
            @unlink($file);
        }
    }

    public function testLoadFromFileNonExistent(): void
    {
        $this->expectException(RuntimeException::class);
        $this->loader->loadFromFile('/path/to/nonexistent/file.yaml');
    }
}
