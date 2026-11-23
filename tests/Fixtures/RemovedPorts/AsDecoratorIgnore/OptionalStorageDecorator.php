<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Fixtures\RemovedPorts\AsDecoratorIgnore;

use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A loadable host decorator of the 1.x asset storage id with `onInvalid: IGNORE` (W1-1): Symfony would drop it without
 * a word once that id is gone.
 */
#[AsDecorator('Nowo\PageBuilderKitBundle\Media\PageBuilderAssetStorageInterface', onInvalid: ContainerInterface::IGNORE_ON_INVALID_REFERENCE)]
final readonly class OptionalStorageDecorator
{
    public function __construct(#[AutowireDecorated] public ?object $inner = null)
    {
    }
}
