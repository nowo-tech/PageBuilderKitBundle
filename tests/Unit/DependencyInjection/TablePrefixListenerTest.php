<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\DependencyInjection;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Nowo\PageBuilderKitBundle\DependencyInjection\TablePrefixListener;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(TablePrefixListener::class)]
final class TablePrefixListenerTest extends TestCase
{
    #[Test]
    public function prefixesBundleEntityTables(): void
    {
        $metadata = new ClassMetadata(BuilderPage::class);
        $metadata->setPrimaryTable(['name' => 'pb_page']);

        $event = new LoadClassMetadataEventArgs($metadata, $this->createStub(EntityManagerInterface::class));

        (new TablePrefixListener('app_'))->loadClassMetadata($event);

        self::assertSame('app_pb_page', $metadata->getTableName());
    }

    #[Test]
    public function ignoresEmptyPrefixAndForeignEntities(): void
    {
        $metadata = new ClassMetadata(BuilderPage::class);
        $metadata->setPrimaryTable(['name' => 'pb_page']);
        $event = new LoadClassMetadataEventArgs($metadata, $this->createStub(EntityManagerInterface::class));

        (new TablePrefixListener(''))->loadClassMetadata($event);
        self::assertSame('pb_page', $metadata->getTableName());

        $foreign = new ClassMetadata(stdClass::class);
        $foreign->setPrimaryTable(['name' => 'other']);
        $foreignEvent = new LoadClassMetadataEventArgs($foreign, $this->createStub(EntityManagerInterface::class));

        (new TablePrefixListener('app_'))->loadClassMetadata($foreignEvent);
        self::assertSame('other', $foreign->getTableName());
    }
}
