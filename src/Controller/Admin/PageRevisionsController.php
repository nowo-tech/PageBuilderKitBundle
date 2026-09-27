<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Controller\Admin;

use InvalidArgumentException;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageRevision;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRepository;
use Nowo\PageBuilderKitBundle\Service\DocumentNormalizer;
use Nowo\PageBuilderKitBundle\Service\PageRevisionService;
use Nowo\PageBuilderKitBundle\Service\PageRevisionStore;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

use function is_string;
use function sprintf;
use function trim;

use const DATE_ATOM;

final class PageRevisionsController extends AbstractController
{
    public const string CSRF_TOKEN_ID = 'page_builder_document';

    public function __construct(
        private readonly BuilderPageRepository $pageRepository,
        private readonly PageRevisionService $revisionService,
        private readonly PageRevisionStore $revisionStore,
        private readonly DocumentNormalizer $documentNormalizer,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route(
        '/pages/{pageKey}/revisions',
        name: 'admin_page_builder_revisions',
        requirements: ['pageKey' => '[a-z0-9_-]+'],
        methods: ['GET'],
    )]
    public function index(string $pageKey): Response
    {
        $page = $this->pageRepository->findOneByPageKey($pageKey);
        if (!$page instanceof BuilderPage) {
            throw $this->createNotFoundException(sprintf('Unknown page "%s".', $pageKey));
        }

        if (!$this->revisionService->isEnabled()) {
            throw $this->createNotFoundException('Page revisions are disabled.');
        }

        $structure = $this->documentNormalizer->normalize($page->getDocument()?->getStructure() ?? []);
        $engine    = ($structure['engine'] ?? null) === DocumentNormalizer::ENGINE_GRAPESJS
            ? 'grapesjs'
            : 'classic';

        return $this->render('@NowoPageBuilderKitBundle/admin/pages/revisions.html.twig', [
            'page'         => $page,
            'page_key'     => $pageKey,
            'revisions'    => $this->revisionService->list($page),
            'engine'       => $engine,
            'max_per_page' => $this->revisionStore->getMaxPerPage(),
            'csrf_token'   => $this->csrfTokenManager->getToken(self::CSRF_TOKEN_ID)->getValue(),
            'create_url'   => $this->generateUrl('admin_page_builder_revisions_create', ['pageKey' => $pageKey]),
        ]);
    }

    #[Route(
        '/pages/{pageKey}/revisions',
        name: 'admin_page_builder_revisions_create',
        requirements: ['pageKey' => '[a-z0-9_-]+'],
        methods: ['POST'],
    )]
    public function create(string $pageKey, Request $request): Response
    {
        if (!$this->validateCsrf($request)) {
            return $this->csrfError($request);
        }

        $page = $this->pageRepository->findOneByPageKey($pageKey);
        if (!$page instanceof BuilderPage) {
            return $this->notFound($request);
        }

        $label = $this->readLabel($request);

        try {
            $revision = $this->revisionService->create($page, $label);
        } catch (InvalidArgumentException $exception) {
            return $this->error($request, $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        if ($request->getPreferredFormat() === 'json' || $request->headers->get('Accept') === 'application/json') {
            return new JsonResponse([
                'ok'       => true,
                'revision' => $revision instanceof BuilderPageRevision ? $this->serializeRevision($revision) : null,
            ]);
        }

        $this->addFlash('success', 'admin.revisions.created');

        return $this->redirectToRoute('admin_page_builder_revisions', ['pageKey' => $pageKey]);
    }

    #[Route(
        '/pages/{pageKey}/revisions/{revisionId}/restore',
        name: 'admin_page_builder_revisions_restore',
        requirements: ['pageKey' => '[a-z0-9_-]+', 'revisionId' => '\d+'],
        methods: ['POST'],
    )]
    public function restore(string $pageKey, int $revisionId, Request $request): Response
    {
        if (!$this->validateCsrf($request)) {
            return $this->csrfError($request);
        }

        $page = $this->pageRepository->findOneByPageKey($pageKey);
        if (!$page instanceof BuilderPage) {
            return $this->notFound($request);
        }

        try {
            $revision = $this->revisionService->restore($page, $revisionId);
        } catch (InvalidArgumentException $exception) {
            return $this->error($request, $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        if ($request->getPreferredFormat() === 'json' || $request->headers->get('Accept') === 'application/json') {
            return new JsonResponse([
                'ok'         => true,
                'revisionId' => $revision->getId(),
            ]);
        }

        $this->addFlash('success', 'admin.revisions.restored');

        $structure = $this->documentNormalizer->normalize($page->getDocument()?->getStructure() ?? []);
        if (($structure['engine'] ?? null) === DocumentNormalizer::ENGINE_GRAPESJS) {
            return $this->redirectToRoute('admin_page_builder_canvas', ['pageKey' => $pageKey]);
        }

        return $this->redirectToRoute('admin_page_builder_sections', ['pageKey' => $pageKey]);
    }

    #[Route(
        '/pages/{pageKey}/revisions.json',
        name: 'admin_page_builder_revisions_json',
        requirements: ['pageKey' => '[a-z0-9_-]+'],
        methods: ['GET'],
    )]
    public function listJson(string $pageKey): JsonResponse
    {
        if (!$this->revisionService->isEnabled()) {
            return new JsonResponse(['error' => 'revisions_disabled'], Response::HTTP_NOT_FOUND);
        }

        $page = $this->pageRepository->findOneByPageKey($pageKey);
        if (!$page instanceof BuilderPage) {
            return new JsonResponse(['error' => 'not_found'], Response::HTTP_NOT_FOUND);
        }

        $items = [];
        foreach ($this->revisionService->list($page) as $revision) {
            $items[] = $this->serializeRevision($revision);
        }

        return new JsonResponse([
            'pageKey'    => $pageKey,
            'revisions'  => $items,
            'maxPerPage' => $this->revisionStore->getMaxPerPage(),
        ]);
    }

    private function validateCsrf(Request $request): bool
    {
        $token = (string) (
            $request->headers->get('X-CSRF-TOKEN')
            ?: $request->request->get('_csrf_token', '')
        );

        return $this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $token));
    }

    private function readLabel(Request $request): ?string
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];
        $label   = $payload['label'] ?? $request->request->get('label');
        if (!is_string($label)) {
            return null;
        }
        $label = trim($label);

        return $label === '' ? null : $label;
    }

    /**
     * @return array{id: int|null, label: string|null, createdAt: string, engine: string}
     */
    private function serializeRevision(BuilderPageRevision $revision): array
    {
        $structure = $this->documentNormalizer->normalize($revision->getStructure());
        $engine    = ($structure['engine'] ?? null) === DocumentNormalizer::ENGINE_GRAPESJS
            ? 'grapesjs'
            : 'classic';

        return [
            'id'        => $revision->getId(),
            'label'     => $revision->getLabel(),
            'createdAt' => $revision->getCreatedAt()->format(DATE_ATOM),
            'engine'    => $engine,
        ];
    }

    private function csrfError(Request $request): Response
    {
        return $this->error($request, 'invalid_csrf', Response::HTTP_FORBIDDEN);
    }

    private function notFound(Request $request): Response
    {
        return $this->error($request, 'not_found', Response::HTTP_NOT_FOUND);
    }

    private function error(Request $request, string $message, int $status): Response
    {
        if ($request->getPreferredFormat() === 'json' || $request->headers->get('Accept') === 'application/json') {
            return new JsonResponse(['error' => $message], $status);
        }

        $this->addFlash('danger', $message);

        return $this->redirectToRoute('admin_page_builder_list');
    }
}
