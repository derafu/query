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

use Derafu\Query\Config\Contract\ConfigLoaderInterface;
use Derafu\Query\Config\YamlConfigLoader;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('invalidRootProvider')]
    public function testRejectsInvalidRoot(string $content): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->loader->loadFromString($content);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidRootProvider(): iterable
    {
        yield 'list' => ["- products"];
        yield 'numeric key' => ["table: products\n123: extra"];
        yield 'quoted numeric key' => ["table: products\n'123': extra"];
        yield 'negative key' => ["-1: products"];
        yield 'scalar root' => ['products'];
    }

    public function testPreservesNestedNumericKeysAndUnknownOptions(): void
    {
        $config = $this->loader->loadFromString(
            "table: products\nselect: [id, name]\nextension:\n  123: [a, b]"
        );

        $this->assertSame([
            'table' => 'products',
            'select' => ['id', 'name'],
            'extension' => [123 => ['a', 'b']],
        ], $config);
    }

    public function testAcceptsEmptyRoot(): void
    {
        $this->assertSame([], $this->loader->loadFromString('{}'));
        $this->assertSame([], $this->loader->loadFromString('[]'));
    }
}
