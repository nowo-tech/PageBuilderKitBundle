<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Fixtures\RemovedPorts\Unrelated;

use Nowo\PageBuilderKitBundle\Media\PageBuilderAssetStorageInterfaceFactory;

/**
 * An unused host class implementing an unrelated missing interface whose name only starts like a removed port's
 * (W1-1: exact FQCN match): its lazy loading error stays lazy and the container compiles. Excluded from PHPStan and
 * Rector.
 */
final class OtherMissingInterfaceUser implements PageBuilderAssetStorageInterfaceFactory
{
}
