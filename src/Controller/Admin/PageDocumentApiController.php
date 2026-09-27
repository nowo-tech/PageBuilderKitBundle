<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Controller\Admin;

use InvalidArgumentException;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRepositoryInterface;
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
use function str_contains;
use function str_starts_with;

final class PageDocumentApiController extends AbstractController
{
    public function __construct(
        private readonly BuilderPageRepositoryInterface $pageRepository,
        private readonly DocumentService $documentService,
        private readonly DocumentNormalizer $documentNormalizer,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('/pages/{pageKey}/document', name: 'admin_page_builder_document_get', requirements: ['pageKey' => '[a-z0-9_-]+'], methods: ['GET'])]
    public function getDocument(string $pageKey): JsonResponse
    {
        $page = $this->pageRepository->findOneByPageKey($pageKey);
        if (!$page instanceof BuilderPage) {
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

    #[Route('/pages/{pageKey}/document', name: 'admin_page_builder_document_save', requirements: ['pageKey' => '[a-z0-9_-]+'], methods: ['POST'])]
    public function saveDocument(string $pageKey, Request $request): JsonResponse
    {
        if (!$this->validateDocumentCsrf($request)) {
            return new JsonResponse(['error' => 'invalid_csrf'], Response::HTTP_FORBIDDEN);
        }

        $page = $this->pageRepository->findOneByPageKey($pageKey);
        if (!$page instanceof BuilderPage) {
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

    #[Route('/pages/{pageKey}/publish', name: 'admin_page_builder_document_publish', requirements: ['pageKey' => '[a-z0-9_-]+'], methods: ['POST'])]
    public function publish(string $pageKey, Request $request): Response
    {
        if (!$this->validateDocumentCsrf($request)) {
            return $this->statusError($request, 'invalid_csrf', Response::HTTP_FORBIDDEN);
        }

        $page = $this->pageRepository->findOneByPageKey($pageKey);
        if (!$page instanceof BuilderPage) {
            return $this->statusError($request, 'not_found', Response::HTTP_NOT_FOUND);
        }

        $this->documentService->publish($page);

        return $this->statusOk($request, $pageKey, $page->getStatus()->value, 'admin.canvas.published_flash');
    }

    #[Route('/pages/{pageKey}/unpublish', name: 'admin_page_builder_document_unpublish', requirements: ['pageKey' => '[a-z0-9_-]+'], methods: ['POST'])]
    public function unpublish(string $pageKey, Request $request): Response
    {
        if (!$this->validateDocumentCsrf($request)) {
            return $this->statusError($request, 'invalid_csrf', Response::HTTP_FORBIDDEN);
        }

        $page = $this->pageRepository->findOneByPageKey($pageKey);
        if (!$page instanceof BuilderPage) {
            return $this->statusError($request, 'not_found', Response::HTTP_NOT_FOUND);
        }

        $this->documentService->unpublish($page);

        return $this->statusOk($request, $pageKey, $page->getStatus()->value, 'admin.canvas.unpublished_flash');
    }

    private function validateDocumentCsrf(Request $request): bool
    {
        $header = (string) $request->headers->get('X-CSRF-TOKEN', '');
        if ($header !== '') {
            return $this->csrfTokenManager->isTokenValid(
                new CsrfToken('page_builder_document', $header),
            );
        }

        return $this->csrfTokenManager->isTokenValid(
            new CsrfToken('page_builder_document', (string) $request->request->get('_csrf_token', '')),
        );
    }

    private function wantsJson(Request $request): bool
    {
        $accept = (string) $request->headers->get('Accept', '');

        return $request->getPreferredFormat() === 'json'
            || str_contains($accept, 'application/json');
    }

    private function statusOk(Request $request, string $pageKey, string $status, string $flash): Response
    {
        if ($this->wantsJson($request)) {
            return new JsonResponse(['ok' => true, 'status' => $status]);
        }

        $this->addFlash('success', $flash);

        $redirect = (string) $request->request->get('_redirect', '');
        if ($redirect !== '' && str_starts_with($redirect, '/')) {
            return $this->redirect($redirect);
        }

        return $this->redirectToRoute('admin_page_builder_canvas', ['pageKey' => $pageKey]);
    }

    private function statusError(Request $request, string $error, int $code): Response
    {
        if ($this->wantsJson($request)) {
            return new JsonResponse(['error' => $error], $code);
        }

        $this->addFlash('danger', $error);

        return $this->redirectToRoute('admin_page_builder_list');
    }
}
