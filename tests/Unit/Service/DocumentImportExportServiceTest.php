<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\PageBuilderKitBundle\Entity\BuilderDocument;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageTranslation;
use Nowo\PageBuilderKitBundle\Enum\HtmlSanitizeStrategy;
use Nowo\PageBuilderKitBundle\Enum\PageStatus;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocales;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRepositoryInterface;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtection;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtectionConfig;
use Nowo\PageBuilderKitBundle\Service\DocumentImportExportService;
use Nowo\PageBuilderKitBundle\Service\DocumentNormalizer;
use Nowo\PageBuilderKitBundle\Service\DocumentService;
use Nowo\PageBuilderKitBundle\Service\WidgetPropsMerger;
use Nowo\PageBuilderKitBundle\Tests\Support\WidgetTypesFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DocumentImportExportService::class)]
final class DocumentImportExportServiceTest extends TestCase
{
    #[Test]
    public function exportAndImportRoundTripCreatesDraft(): void
    {
        $page = (new BuilderPage())
            ->setPageKey('home')
            ->setStatus(PageStatus::Published)
            ->setUuid('11111111-1111-4111-8111-111111111111');
        $page->addTranslation(
            (new BuilderPageTranslation())->setLocale('es')->setTitle('Inicio')->setSlug('inicio'),
        );
        $document = (new BuilderDocument())->setPage($page)->setStructure([
            'version'       => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
            'engine'        => DocumentNormalizer::ENGINE_GRAPESJS,
            'html'          => '<p>Hi</p>',
            'css'           => '',
            'grapes'        => [],
            'localeContent' => [],
        ]);
        $page->setDocument($document);

        $store = [];
        $repo  = new class($store) implements BuilderPageRepositoryInterface {
            /** @param array<string, BuilderPage> $store */
            public function __construct(private array &$store)
            {
            }

            public function findOneByPageKey(string $pageKey): ?BuilderPage
            {
                return $this->store[$pageKey] ?? null;
            }

            public function findAllOrdered(): array
            {
                return array_values($this->store);
            }

            public function put(BuilderPage $page): void
            {
                $this->store[$page->getPageKey()] = $page;
            }
        };
        $repo->put($page);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use ($repo): void {
            if ($entity instanceof BuilderPage) {
                $repo->put($entity);
            }
        });

        $documents = new DocumentService(
            $repo,
            $em,
            new BuilderLocales('es', ['es', 'en']),
            new DocumentNormalizer(),
            WidgetTypesFixture::registry(),
            new PageBuilderProtection(new PageBuilderProtectionConfig(HtmlSanitizeStrategy::None, null)),
            new WidgetPropsMerger(),
        );

        $service = new DocumentImportExportService($documents, new DocumentNormalizer(), new BuilderLocales('es', ['es', 'en']));
        $payload = $service->export($page);

        self::assertSame(1, $payload['formatVersion']);
        self::assertSame('home', $payload['pageKey']);

        $imported = $service->import($payload, 'home-import');
        self::assertSame('home-import', $imported->getPageKey());
        self::assertSame(PageStatus::Draft, $imported->getStatus());
    }
}
