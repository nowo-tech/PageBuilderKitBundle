<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Fixtures\RemovedPorts\AsDecoratorStorage;

use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * A loadable host decorator of the 1.x asset storage id (removed in 2.0) that checked uploads (W1-1): dropped, uploads
 * would no longer be checked by it.
 */
#[AsDecorator('Nowo\PageBuilderKitBundle\Media\PageBuilderAssetStorageInterface')]
final readonly class CheckingStorageDecorator
{
    public function __construct(#[AutowireDecorated] public ?object $inner = null)
    {
    }
}
