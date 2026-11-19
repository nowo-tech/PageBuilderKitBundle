<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Integration\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Tools\DsnParser;

/**
 * Plain DBAL connections to the real test servers (REQ-RUNTIME-003): the second session of the race tests.
 */
final class DatabaseUrl
{
    public static function connect(string $url): Connection
    {
        $params = new DsnParser(['mysql' => 'pdo_mysql', 'pgsql' => 'pdo_pgsql', 'postgresql' => 'pdo_pgsql'])->parse($url);

        return DriverManager::getConnection($params);
    }

    /**
     * Bounds every lock wait of the session, so a hang fails fast instead of holding the machine.
     */
    public static function limitLockWait(Connection $connection, int $seconds): void
    {
        if ($connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $connection->executeStatement('SET lock_timeout = ' . ($seconds * 1000));

            return;
        }

        $connection->executeStatement('SET SESSION innodb_lock_wait_timeout = ' . $seconds);
    }
}
