<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Locale;

use Nowo\PageBuilderKitBundle\Locale\BuilderLocales;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocalesLegacyBinding;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BuilderLocalesLegacyBinding::class)]
final class BuilderLocalesLegacyBindingTest extends TestCase
{
    #[Test]
    public function bindStoresInstanceUntilReset(): void
    {
        $binding = new BuilderLocalesLegacyBinding();
        $locales = new BuilderLocales('en', ['en']);

        $binding->bind($locales);

        self::assertSame($locales, $binding->getBoundInstance());

        $binding->reset();

        self::assertNull($binding->getBoundInstance());
    }
}
