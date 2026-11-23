<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Fixtures\RemovedPorts\AsAliasStorage;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * A loadable host class aliased to the id of the asset storage port removed in 2.0 (W1-1): 2.0 never reads that id, so
 * the host's storage would silently not be used.
 */
#[AsAlias('Nowo\PageBuilderKitBundle\Media\PageBuilderAssetStorageInterface')]
final class HostAssetStorage
{
}
