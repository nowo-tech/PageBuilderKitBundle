<?php

declare(strict_types=1);

/*
 * Second database session of the race tests (REQ-RUNTIME-003), in its own process so it really runs at the same time:
 * opens a transaction, runs the statements (locks, inserts, updates), writes the signal file, holds the transaction
 * open for hold_ms, then commits. Usage: php hold-and-commit.php '<json: url, statements [[sql, params]], signal, hold_ms>'
 */

use Nowo\PageBuilderKitBundle\Tests\Integration\Database\DatabaseUrl;

require dirname(__DIR__, 4) . '/vendor/autoload.php';

/** @var array{url: string, statements: list<array{0: string, 1: list<mixed>}>, signal: string, hold_ms: int} $config */
$config     = json_decode($argv[1] ?? '{}', true, 512, \JSON_THROW_ON_ERROR);
$connection = DatabaseUrl::connect($config['url']);
DatabaseUrl::limitLockWait($connection, 10);
$connection->beginTransaction();
foreach ($config['statements'] as [$sql, $params]) {
    str_starts_with($sql, 'SELECT') ? $connection->executeQuery($sql, $params)->fetchAllAssociative() : $connection->executeStatement($sql, $params);
}
file_put_contents($config['signal'], 'held');
usleep($config['hold_ms'] * 1000);
$connection->commit();
$connection->close();
