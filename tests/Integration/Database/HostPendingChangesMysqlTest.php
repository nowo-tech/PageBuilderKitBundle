<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Integration\Database;

use Nowo\PageBuilderKitBundle\Tests\Integration\Persistence\HostPendingChangesTestCase;
use PHPUnit\Framework\Attributes\Group;

use function getenv;
use function is_string;

/**
 * {@see HostPendingChangesTestCase} on MySQL 8.4 (CI job "Real databases", DBAL 4 and 3): `PBK_MYSQL_URL`, else skipped.
 */
#[Group('database')]
final class HostPendingChangesMysqlTest extends HostPendingChangesTestCase
{
    protected function databaseUrl(): string
    {
        $url = getenv('PBK_MYSQL_URL');
        if (!is_string($url) || $url === '') {
            self::markTestSkipped('PBK_MYSQL_URL is not set (make test-with-db).');
        }

        return $url;
    }
}
