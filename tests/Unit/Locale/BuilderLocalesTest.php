<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Locale;

use Nowo\PageBuilderKitBundle\Locale\BuilderLocales;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocalesLegacyBinding;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BuilderLocales::class)]
#[CoversClass(BuilderLocalesLegacyBinding::class)]
final class BuilderLocalesTest extends TestCase
{
    #[Test]
    public function getDefaultAndGetAll(): void
    {
        $locales = new BuilderLocales('es', ['es', 'en', 'fr']);

        self::assertSame('es', $locales->getDefault());
        self::assertSame(['es', 'en', 'fr'], $locales->getAll());
    }

    #[Test]
    public function legacyBindingStoresInstanceUntilReset(): void
    {
        $locales = new BuilderLocales('en', ['en']);
        $binding = new BuilderLocalesLegacyBinding();

        $binding->bind($locales);

        self::assertSame($locales, $binding->getBoundInstance());

        $binding->reset();

        self::assertNull($binding->getBoundInstance());
    }
}
