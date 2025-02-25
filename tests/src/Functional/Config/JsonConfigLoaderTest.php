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
use Derafu\Query\Config\JsonConfigLoader;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(JsonConfigLoader::class)]
class JsonConfigLoaderTest extends TestCase
{
    private ConfigLoaderInterface $loader;

    protected function setUp(): void
    {
        $this->loader = new JsonConfigLoader();
    }

    public function testLoadFromString(): void
    {
        $json = <<<'JSON'
{
    "table": "customers",
    "select": "*",
    "where": "status?=active"
}
JSON;

        $config = $this->loader->loadFromString($json);

        $this->assertIsArray($config);
        $this->assertSame('customers', $config['table']);
        $this->assertSame('*', $config['select']);
        $this->assertSame('status?=active', $config['where']);
    }

    public function testLoadFromStringInvalidJson(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->loader->loadFromString('{"table": "customers",}');
    }

    public function testLoadFromFile(): void
    {
        // Create temporary file.
        $file = tempnam(sys_get_temp_dir(), 'json_test_');
        $json = <<<'JSON'
{
    "table": "invoices",
    "select": "id, number",
    "where": "status?=paid"
}
JSON;
        file_put_contents($file, $json);

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
        $this->loader->loadFromFile('/path/to/nonexistent/file.json');
    }
}
