<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Media;

use Nowo\PageBuilderKitBundle\Media\AssetUploadResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AssetUploadResult::class)]
final class AssetUploadResultTest extends TestCase
{
    #[Test]
    public function toGrapesAssetIncludesOptionalDimensions(): void
    {
        $full = new AssetUploadResult('https://cdn/a.png', 'a.png', 'image', 100, 50, 'image/png', 'a.png');
        self::assertSame([
            'src'    => 'https://cdn/a.png',
            'type'   => 'image',
            'name'   => 'a.png',
            'width'  => 100,
            'height' => 50,
        ], $full->toGrapesAsset());

        $minimal = new AssetUploadResult('/uploads/a.png', 'a.png');
        self::assertSame([
            'src'  => '/uploads/a.png',
            'type' => 'image',
            'name' => 'a.png',
        ], $minimal->toGrapesAsset());
    }
}
