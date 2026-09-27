<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Security;

use Nowo\PageBuilderKitBundle\Enum\HtmlSanitizeStrategy;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtectionConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PageBuilderProtectionConfig::class)]
final class PageBuilderProtectionConfigTest extends TestCase
{
    #[Test]
    public function exposesStrategyAndOptionalService(): void
    {
        $config = new PageBuilderProtectionConfig(HtmlSanitizeStrategy::Strip, 'app.sanitizer');

        self::assertSame(HtmlSanitizeStrategy::Strip, $config->htmlSanitizeStrategy);
        self::assertSame('app.sanitizer', $config->htmlSanitizeService);
    }
}
