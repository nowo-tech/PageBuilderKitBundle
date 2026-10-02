<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Controller\Admin;

use InvalidArgumentException;
use Nowo\PageBuilderKitBundle\Debug\NullPageBuilderKitTrace;
use Nowo\PageBuilderKitBundle\Debug\PageBuilderKitTraceInterface;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRepositoryInterface;
use Nowo\PageBuilderKitBundle\Service\DocumentImportExportService;
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
use function is_string;
use function str_contains;
use function str_starts_with;

final class PageDocumentApiController extends AbstractController
{
    public function __construct(
        private readonly BuilderPageRepositoryInterface $pageRepository,
        private readonly DocumentService $documentService,
        private readonly DocumentNormalizer $documentNormalizer,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly DocumentImportExportService $importExportService,
        private readonly PageBuilderKitTraceInterface $trace = new NullPageBuilderKitTrace(),
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

        // @igor-ignore - Request-scoped debug trace; ResetInterface clears between worker requests.
        $this->trace->addAdminAction('save', $pageKey);

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

        try {
            $this->documentService->publish($page);
        } catch (InvalidArgumentException $exception) {
            return $this->statusError($request, $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
        // @igor-ignore - Request-scoped debug trace; ResetInterface clears between worker requests.
        $this->trace->addAdminAction('publish', $pageKey);

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
        // @igor-ignore - Request-scoped debug trace; ResetInterface clears between worker requests.
        $this->trace->addAdminAction('unpublish', $pageKey);

        return $this->statusOk($request, $pageKey, $page->getStatus()->value, 'admin.canvas.unpublished_flash');
    }

    #[Route('/pages/{pageKey}/duplicate', name: 'admin_page_builder_document_duplicate', requirements: ['pageKey' => '[a-z0-9_-]+'], methods: ['POST'])]
    public function duplicate(string $pageKey, Request $request): Response
    {
        if (!$this->validateDocumentCsrf($request)) {
            return $this->statusError($request, 'invalid_csrf', Response::HTTP_FORBIDDEN);
        }

        $page = $this->pageRepository->findOneByPageKey($pageKey);
        if (!$page instanceof BuilderPage) {
            return $this->statusError($request, 'not_found', Response::HTTP_NOT_FOUND);
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];
        $newKey  = is_string($payload['pageKey'] ?? null)
            ? $payload['pageKey']
            : (string) $request->request->get('pageKey', $pageKey . '-copy');
        $title = is_string($payload['title'] ?? null)
            ? $payload['title']
            : (is_string($request->request->get('title')) ? $request->request->get('title') : null);

        try {
            $clone = $this->documentService->duplicatePage($page, $newKey, $title);
        } catch (InvalidArgumentException $exception) {
            return $this->statusError($request, $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        // @igor-ignore - Request-scoped debug trace; ResetInterface clears between worker requests.
        $this->trace->addAdminAction('duplicate', $clone->getPageKey());

        if ($this->wantsJson($request)) {
            return new JsonResponse(['ok' => true, 'pageKey' => $clone->getPageKey()]);
        }

        $this->addFlash('success', 'admin.pages.duplicated');

        return $this->redirectToRoute('admin_page_builder_canvas', ['pageKey' => $clone->getPageKey()]);
    }

    #[Route('/pages/{pageKey}/export', name: 'admin_page_builder_document_export', requirements: ['pageKey' => '[a-z0-9_-]+'], methods: ['GET'])]
    public function export(string $pageKey): JsonResponse
    {
        $page = $this->pageRepository->findOneByPageKey($pageKey);
        if (!$page instanceof BuilderPage) {
            return new JsonResponse(['error' => 'not_found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $payload = $this->importExportService->export($page);
        } catch (InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        // @igor-ignore - Request-scoped debug trace; ResetInterface clears between worker requests.
        $this->trace->addAdminAction('export', $pageKey);

        return new JsonResponse($payload);
    }

    #[Route('/pages/import', name: 'admin_page_builder_document_import', methods: ['POST'])]
    public function import(Request $request): Response
    {
        if (!$this->validateDocumentCsrf($request)) {
            return $this->statusError($request, 'invalid_csrf', Response::HTTP_FORBIDDEN);
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];
        if ($payload === [] && $request->request->has('payload')) {
            $decoded = json_decode((string) $request->request->get('payload'), true);
            $payload = is_array($decoded) ? $decoded : [];
        }

        $targetKey = is_string($payload['targetPageKey'] ?? null) ? $payload['targetPageKey'] : null;
        $publish   = (bool) ($payload['publish'] ?? false);
        unset($payload['targetPageKey'], $payload['publish']);

        try {
            $page = $this->importExportService->import($payload, $targetKey, $publish);
        } catch (InvalidArgumentException $exception) {
            return $this->statusError($request, $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        // @igor-ignore - Request-scoped debug trace; ResetInterface clears between worker requests.
        $this->trace->addAdminAction('import', $page->getPageKey());

        if ($this->wantsJson($request)) {
            return new JsonResponse(['ok' => true, 'pageKey' => $page->getPageKey()]);
        }

        $this->addFlash('success', 'admin.pages.imported');

        return $this->redirectToRoute('admin_page_builder_list');
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
