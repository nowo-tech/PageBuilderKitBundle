<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        __DIR__ . '/demo',
        // 2.0 (W1-1): fixtures that implement the ports removed in 2.0 (or an unrelated missing interface) on purpose.
        __DIR__ . '/tests/Fixtures/RemovedPorts/ImplementsStorage',
        __DIR__ . '/tests/Fixtures/RemovedPorts/ImplementsContext',
        __DIR__ . '/tests/Fixtures/RemovedPorts/Unrelated',
    ])
    // Machine-wide cap: at most 2 worker processes; generous timeout for slow (WSL2 / CI) hosts.
    ->withParallel(300, 2, 10)
    ->withPhpSets(php84: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
    );
