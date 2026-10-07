<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Content;

use Nowo\PageBuilderKitBundle\Content\NullContentFieldDefinitionProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NullContentFieldDefinitionProviderTest extends TestCase
{
    #[Test]
    public function returnsNoDefinitions(): void
    {
        self::assertSame([], (new NullContentFieldDefinitionProvider())->definitions('home'));
    }
}
