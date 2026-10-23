<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsQuery\Fixture;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use RuntimeException;
use Throwable;

/**
 * An isolated real database server, following derafu/auth's RealKeycloak pattern.
 * Credentials belong only to the disposable container. No host data is mounted.
 */
final class RealDatabaseContainer
{
    public const POSTGRESQL_IMAGE = 'postgres:18-alpine';

    public const MYSQL_IMAGE = 'mysql:8.4';

    private bool $stopped = false;

    /** @param array{driver: 'pdo_mysql'|'pdo_pgsql', host: string, port: int, user: string, password: string, dbname: string} $parameters */
    private function __construct(
        private readonly string $container,
        private readonly array $parameters
    ) {
    }

    public static function postgresql(): self
    {
        return self::start(self::POSTGRESQL_IMAGE, 5432, 'pdo_pgsql', 'postgres', [
            'POSTGRES_PASSWORD=query_test_password',
            'POSTGRES_DB=query_tests',
        ]);
    }

    public static function mysql(): self
    {
        return self::start(self::MYSQL_IMAGE, 3306, 'pdo_mysql', 'root', [
            'MYSQL_ROOT_PASSWORD=query_test_password',
            'MYSQL_ROOT_HOST=%',
            'MYSQL_DATABASE=query_tests',
        ]);
    }

    /**
     * @param 'pdo_mysql'|'pdo_pgsql' $driver
     * @param list<string> $environment
     */
    private static function start(string $image, int $port, string $driver, string $user, array $environment): self
    {
        $name = 'derafu-query-tests-' . bin2hex(random_bytes(8));
        $command = 'docker run -d --rm --name ' . escapeshellarg($name)
            . ' --publish ' . escapeshellarg('127.0.0.1::' . $port);
        foreach ($environment as $variable) {
            $command .= ' --env ' . escapeshellarg($variable);
        }
        exec($command . ' ' . escapeshellarg($image) . ' 2>&1', $output, $status);
        if ($status !== 0) {
            throw new RuntimeException("Docker could not start the test database:\n" . implode("\n", $output));
        }

        exec('docker port ' . escapeshellarg($name) . ' ' . $port . '/tcp 2>&1', $bindings, $status);
        $binding = implode('', $bindings);
        if ($status !== 0 || preg_match('/^127\.0\.0\.1:([0-9]+)$/D', $binding, $matches) !== 1) {
            exec('docker stop -t 1 ' . escapeshellarg($name) . ' 2>&1');
            throw new RuntimeException('Docker did not publish a local database port: ' . $binding);
        }

        $server = new self($name, [
            'driver' => $driver,
            'host' => '127.0.0.1',
            'port' => (int) $matches[1],
            'user' => $user,
            'password' => 'query_test_password',
            'dbname' => 'query_tests',
        ]);
        register_shutdown_function($server->stop(...));

        $lastError = '';
        for ($attempt = 0; $attempt < 120; $attempt++) {
            $connection = DriverManager::getConnection($server->parameters);
            try {
                $connection->executeQuery('SELECT 1');
                return $server;
            } catch (Throwable $exception) {
                $lastError = $exception->getMessage();
            } finally {
                $connection->close();
            }
            sleep(1);
        }

        exec('docker logs ' . escapeshellarg($name) . ' 2>&1', $logs);
        $server->stop();
        throw new RuntimeException("Database did not become ready in two minutes: {$lastError}\n" . implode("\n", $logs));
    }

    /** Each scenario gets a fresh database inside this test container. */
    public function database(): Connection
    {
        $parameters = $this->parameters;
        $parameters['dbname'] = 'query_' . bin2hex(random_bytes(8));
        $admin = DriverManager::getConnection($this->parameters);
        try {
            $admin->executeStatement('CREATE DATABASE ' . $admin->quoteIdentifier($parameters['dbname']));
        } finally {
            $admin->close();
        }
        return DriverManager::getConnection($parameters);
    }

    /** Drops only the isolated database created for this scenario. */
    public function dropDatabase(Connection $connection): void
    {
        $database = $connection->getDatabase();
        $connection->close();
        $admin = DriverManager::getConnection($this->parameters);
        try {
            if ($database !== null && str_starts_with($database, 'query_') && $database !== 'query_tests') {
                $admin->executeStatement('DROP DATABASE ' . $admin->quoteIdentifier($database));
            }
        } finally {
            $admin->close();
        }
    }

    /** Remove the container and its anonymous data volumes, including on failure. */
    public function stop(): void
    {
        if (!$this->stopped) {
            exec('docker stop -t 1 ' . escapeshellarg($this->container) . ' 2>&1');
            $this->stopped = true;
        }
    }
}
