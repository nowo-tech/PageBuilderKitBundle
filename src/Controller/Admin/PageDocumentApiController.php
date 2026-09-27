<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Controller\Admin;

use InvalidArgumentException;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRepository;
use Nowo\PageBuilderKitBundle\Service\DocumentNormalizer;
use Nowo\PageBuilderKitBundle\Service\DocumentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

use function is_array;

final class PageDocumentApiController extends AbstractController
{
    public function __construct(
        private readonly BuilderPageRepository $pageRepository,
        private readonly DocumentService $documentService,
        private readonly DocumentNormalizer $documentNormalizer,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('/admin/page-builder/pages/{pageKey}/document', name: 'admin_page_builder_document_get', requirements: ['pageKey' => '[a-z0-9_-]+'], methods: ['GET'])]
    public function getDocument(string $pageKey): JsonResponse
    {
        $page = $this->pageRepository->findOneByPageKey($pageKey);
        if ($page === null) {
            return new JsonResponse(['error' => 'not_found'], Response::HTTP_NOT_FOUND);
        }

        $document            = $page->getDocument();
        $widgetPropsByLocale = [];
        foreach ($document?->getLocales() ?? [] as $localeDocument) {
            $widgetPropsByLocale[$localeDocument->getLocale()] = $localeDocument->getWidgetProps();
        }

        return new JsonResponse([
            'pageKey'             => $pageKey,
            'status'              => $page->getStatus()->value,
            'structure'           => $this->documentNormalizer->normalize($document?->getStructure() ?? []),
            'widgetPropsByLocale' => $widgetPropsByLocale,
        ]);
    }

    #[Route('/admin/page-builder/pages/{pageKey}/document', name: 'admin_page_builder_document_save', requirements: ['pageKey' => '[a-z0-9_-]+'], methods: ['POST'])]
    public function saveDocument(string $pageKey, Request $request): JsonResponse
    {
        if (!$this->validateDocumentCsrf($request)) {
            return new JsonResponse(['error' => 'invalid_csrf'], Response::HTTP_FORBIDDEN);
        }

        $page = $this->pageRepository->findOneByPageKey($pageKey);
        if ($page === null) {
            return new JsonResponse(['error' => 'not_found'], Response::HTTP_NOT_FOUND);
        }

        /** @var array<string, mixed> $payload */
        $payload             = json_decode($request->getContent(), true) ?? [];
        $structure           = $payload['structure'] ?? null;
        $widgetPropsByLocale = $payload['widgetPropsByLocale'] ?? null;

        if (!is_array($structure) || !is_array($widgetPropsByLocale)) {
            return new JsonResponse(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->documentService->saveDocument($page, $structure, $widgetPropsByLocale);
        } catch (InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(['ok' => true]);
    }

    #[Route('/admin/page-builder/pages/{pageKey}/publish', name: 'admin_page_builder_document_publish', requirements: ['pageKey' => '[a-z0-9_-]+'], methods: ['POST'])]
    public function publish(string $pageKey, Request $request): JsonResponse
    {
        if (!$this->validateDocumentCsrf($request)) {
            return new JsonResponse(['error' => 'invalid_csrf'], Response::HTTP_FORBIDDEN);
        }

        $page = $this->pageRepository->findOneByPageKey($pageKey);
        if ($page === null) {
            return new JsonResponse(['error' => 'not_found'], Response::HTTP_NOT_FOUND);
        }

        $this->documentService->publish($page);

        return new JsonResponse(['ok' => true, 'status' => $page->getStatus()->value]);
    }

    private function validateDocumentCsrf(Request $request): bool
    {
        return $this->csrfTokenManager->isTokenValid(
            new CsrfToken('page_builder_document', (string) $request->headers->get('X-CSRF-TOKEN', '')),
        );
    }
}
