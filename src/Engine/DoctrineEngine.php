<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Engine;

use Derafu\Query\Engine\Abstract\AbstractSqlEngine;
use Derafu\Query\Engine\Contract\SqlEngineInterface;
use Doctrine\DBAL\Connection as DoctrineConnection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use PDO;
use RuntimeException;

/**
 * Doctrine implementation of the SQL execution engine.
 *
 * This class provides a Doctrine DBAL-based implementation for executing SQL
 * queries.
 *
 * It handles:
 *
 *   - Safe parameter binding.
 *   - Result fetching in associative array format.
 *   - Connection management.
 *   - Error handling through Doctrine DBAL's exception mode (automatically
 *     throws exceptions on errors because of the way Doctrine DBAL configures
 *     the PDO connection).
 */
final class DoctrineEngine extends AbstractSqlEngine implements SqlEngineInterface
{
    /**
     * Creates a new Doctrine engine instance.
     *
     * @param DoctrineConnection $connection The Doctrine DBAL connection instance.
     */
    public function __construct(private readonly DoctrineConnection $connection)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function execute(string $sql, array $parameters = []): array
    {
        return $this->connection->fetchAllAssociative($sql, $parameters);
    }

    /**
     * {@inheritDoc}
     */
    public function getConnection(): DoctrineConnection
    {
        return $this->connection;
    }

    /**
     * {@inheritDoc}
     */
    public function getDriver(): string
    {
        $platform = $this->connection->getDatabasePlatform();

        return match (true) {
            $platform instanceof MySQLPlatform => 'mysql',
            $platform instanceof PostgreSQLPlatform => 'pgsql',
            $platform instanceof SQLitePlatform => 'sqlite',
            $platform instanceof SQLServerPlatform => 'sqlsrv',
            $platform instanceof OraclePlatform => 'oci',
            default => throw new RuntimeException('Unsupported database platform'),
        };
    }

    /**
     * {@inheritDoc}
     */
    public function executeSqlDump(string $sql): void
    {
        $nativeConnection = $this->connection->getNativeConnection();

        if (!$nativeConnection instanceof PDO) {
            throw new RuntimeException(
                'Doctrine DBAL native connection is not a PDO instance.'
            );
        }

        $nativeConnection->exec($sql);
    }
}
