<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Grapes;

use Acme\GrapesBlockPack\AcmeMarketingBlockPack;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function dirname;

/**
 * Smoke-test the in-repo example pack so the recipe stays loadable.
 */
final class ExampleAcmeGrapesBlockPackTest extends TestCase
{
    #[Test]
    public function exposesStablePackMetadataAndBlocks(): void
    {
        require_once dirname(__DIR__, 3) . '/examples/acme-grapes-block-pack/src/AcmeMarketingBlockPack.php';

        $pack = new AcmeMarketingBlockPack();

        self::assertSame('acme/marketing-blocks', $pack->getName());
        self::assertSame('1.0.0', $pack->getVersion());
        self::assertContains('marketing', $pack->getCapabilities());

        $blocks = $pack->getBlocks();
        self::assertNotEmpty($blocks);
        foreach ($blocks as $block) {
            self::assertNotSame('', $block['id']);
            self::assertNotSame('', $block['label']);
            self::assertNotSame('', $block['category']);
            self::assertTrue(isset($block['content']));
        }
    }
}
