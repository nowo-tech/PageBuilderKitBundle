<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Nowo\PageBuilderKitBundle\Entity\BuilderDocument;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageTemplate;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageTemplateRepositoryInterface;

use function is_array;
use function preg_match;
use function sprintf;
use function strtolower;
use function trim;

final readonly class PageTemplateService
{
    public function __construct(
        private BuilderPageTemplateRepositoryInterface $templateRepository,
        private EntityManagerInterface $entityManager,
        private DocumentService $documentService,
        private DocumentNormalizer $documentNormalizer,
    ) {
    }

    /**
     * @return list<BuilderPageTemplate>
     */
    public function list(): array
    {
        return $this->templateRepository->findAllOrdered();
    }

    public function findByKey(string $templateKey): ?BuilderPageTemplate
    {
        return $this->templateRepository->findOneByTemplateKey($templateKey);
    }

    public function saveFromPage(BuilderPage $page, string $templateKey, string $label): BuilderPageTemplate
    {
        $templateKey = strtolower(trim($templateKey));
        if ($templateKey === '' || !preg_match('/^[a-z0-9_-]+$/', $templateKey)) {
            throw new InvalidArgumentException('Invalid template key.');
        }

        $label = trim($label);
        if ($label === '') {
            throw new InvalidArgumentException('Template label is required.');
        }

        $document = $page->getDocument();
        if (!$document instanceof BuilderDocument) {
            throw new InvalidArgumentException(sprintf('Page "%s" has no document.', $page->getPageKey()));
        }

        $structure = $this->documentNormalizer->normalize($document->getStructure());
        $props     = [];
        foreach ($document->getLocales() as $localeDocument) {
            $props[$localeDocument->getLocale()] = $localeDocument->getWidgetProps();
        }

        $template = $this->templateRepository->findOneByTemplateKey($templateKey);
        if (!$template instanceof BuilderPageTemplate) {
            $template = (new BuilderPageTemplate())->setTemplateKey($templateKey);
        }

        $template
            ->setLabel($label)
            ->setStructure($structure)
            ->setWidgetPropsByLocale($props);

        $this->entityManager->persist($template);
        $this->entityManager->flush();

        return $template;
    }

    public function createPageFromTemplate(string $templateKey, string $pageKey, string $title, string $locale): BuilderPage
    {
        $template = $this->templateRepository->findOneByTemplateKey($templateKey);
        if (!$template instanceof BuilderPageTemplate) {
            throw new InvalidArgumentException(sprintf('Unknown template "%s".', $templateKey));
        }

        $page = $this->documentService->createPage($pageKey, $title, $locale);

        /** @var array<string, array<string, mixed>> $props */
        $props = [];
        foreach ($template->getWidgetPropsByLocale() as $loc => $raw) {
            if (is_array($raw)) {
                /* @var array<string, mixed> $raw */
                $props[(string) $loc] = $raw;
            }
        }

        $this->documentService->saveDocument($page, $template->getStructure(), $props);

        return $page;
    }

    public function delete(string $templateKey): void
    {
        $template = $this->templateRepository->findOneByTemplateKey($templateKey);
        if (!$template instanceof BuilderPageTemplate) {
            throw new InvalidArgumentException(sprintf('Unknown template "%s".', $templateKey));
        }

        $this->entityManager->remove($template);
        $this->entityManager->flush();
    }
}
