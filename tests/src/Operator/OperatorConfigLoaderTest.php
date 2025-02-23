<?php

declare(strict_types=1);

/**
 * Derafu: Query - Smart Query Builder.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsQuery\Operator;

use Derafu\Query\Operator\Contract\OperatorConfigInterface;
use Derafu\Query\Operator\Contract\OperatorConfigLoaderInterface;
use Derafu\Query\Operator\OperatorConfig;
use Derafu\Query\Operator\OperatorConfigLoader;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Tests for the YAML operator configuration loader.
 *
 * These tests verify the loader can properly load and validate operator
 * configurations from both YAML files and arrays.
 */
#[CoversClass(OperatorConfigLoader::class)]
#[CoversClass(OperatorConfig::class)]
final class OperatorConfigLoaderTest extends TestCase
{
    /**
     * The loader instance being tested.
     *
     * @var OperatorConfigLoaderInterface
     */
    private OperatorConfigLoaderInterface $loader;

    /**
     * Path to test YAML files.
     *
     * @var string
     */
    private string $fixturesPath;

    /**
     * Sets up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->loader = new OperatorConfigLoader();
        $this->fixturesPath = __DIR__ . '/../../fixtures';
    }

    /**
     * Tests loading a valid configuration file.
     */
    public function testLoadValidFile(): void
    {
        $configs = $this->loader->loadFromFile(
            $this->fixturesPath . '/operators-valid.yaml'
        );

        $this->assertIsArray($configs);
        $this->assertNotEmpty($configs);
        $this->assertContainsOnlyInstancesOf(
            OperatorConfigInterface::class,
            $configs
        );

        // Test a standard operator (=).
        $equalsOp = $configs['='];
        $this->assertSame('=', $equalsOp->getSymbol());
        $this->assertSame('standard', $equalsOp->getType());
        $this->assertSame('{{column}} = {{value}}', $equalsOp->getSqlTemplates()['default']);

        // Test a complex operator (~).
        $regexOp = $configs['~'];
        $this->assertSame('~', $regexOp->getSymbol());
        $this->assertSame('regexp', $regexOp->getType());
        $this->assertArrayHasKey('pgsql', $regexOp->getSqlTemplates());
        $this->assertArrayHasKey('mysql', $regexOp->getSqlTemplates());
    }

    /**
     * Tests loading configuration from array.
     */
    public function testLoadFromArray(): void
    {
        $config = [
            'types' => [
                'standard' => [
                    'name' => 'Standard Operators',
                    'description' => 'Basic comparison operators',
                ],
            ],
            'operators' => [
                '=' => [
                    'type' => 'standard',
                    'name' => 'Equals',
                    'description' => 'Exact value match',
                    'sql' => '{{column}} = {{value}}',
                ],
            ],
        ];

        $configs = $this->loader->loadFromArray($config);

        $this->assertCount(1, $configs);
        $this->assertArrayHasKey('=', $configs);

        $equalsOp = $configs['='];
        $this->assertSame('=', $equalsOp->getSymbol());
        $this->assertSame('standard', $equalsOp->getType());
    }

    /**
     * Tests trying to load a non-existent file.
     */
    public function testLoadNonExistentFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->loader->loadFromFile('/path/to/nonexistent.yaml');
    }

    /**
     * Tests loading invalid YAML content.
     */
    public function testLoadInvalidYaml(): void
    {
        $this->expectException(RuntimeException::class);
        $this->loader->loadFromFile($this->fixturesPath . '/operators-invalid-yaml.yaml');
    }

    /**
     * Tests loading configuration with missing required sections.
     */
    public function testLoadMissingSections(): void
    {
        $this->expectException(RuntimeException::class);
        $this->loader->loadFromArray(['some' => 'config']);
    }

    /**
     * Tests loading configuration with undefined operator type.
     */
    public function testLoadUndefinedType(): void
    {
        $config = [
            'types' => [
                'standard' => ['description' => 'test'],
            ],
            'operators' => [
                '=' => [
                    'type' => 'undefined_type',
                    'name' => 'test',
                    'description' => 'test',
                ],
            ],
        ];

        $this->expectException(RuntimeException::class);
        $this->loader->loadFromArray($config);
    }

    /**
     * Tests loading configuration with missing required fields.
     */
    public function testLoadMissingRequiredFields(): void
    {
        $config = [
            'types' => [
                'standard' => ['description' => 'test'],
            ],
            'operators' => [
                '=' => [
                    'type' => 'standard',
                    // Missing name and description.
                ],
            ],
        ];

        $this->expectException(RuntimeException::class);
        $this->loader->loadFromArray($config);
    }

    /**
     * Tests loading configuration with invalid SQL templates.
     */
    public function testLoadInvalidSqlTemplates(): void
    {
        $config = [
            'types' => [
                'standard' => ['description' => 'test'],
            ],
            'operators' => [
                '=' => [
                    'type' => 'standard',
                    'name' => 'Equals',
                    'description' => 'test',
                    'sql' => [], // Empty SQL templates.
                ],
            ],
        ];

        $this->expectException(RuntimeException::class);
        $this->loader->loadFromArray($config);
    }
}
