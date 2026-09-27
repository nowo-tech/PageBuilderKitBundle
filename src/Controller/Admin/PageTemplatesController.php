<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Controller\Admin;

use InvalidArgumentException;
use Nowo\PageBuilderKitBundle\Debug\NullPageBuilderKitTrace;
use Nowo\PageBuilderKitBundle\Debug\PageBuilderKitTraceInterface;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocales;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRepository;
use Nowo\PageBuilderKitBundle\Service\PageTemplateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

use function is_string;
use function trim;

final class PageTemplatesController extends AbstractController
{
    public const string CSRF_TOKEN_ID = 'page_builder_document';

    public function __construct(
        private readonly PageTemplateService $templateService,
        private readonly BuilderPageRepository $pageRepository,
        private readonly BuilderLocales $builderLocales,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly PageBuilderKitTraceInterface $trace = new NullPageBuilderKitTrace(),
    ) {
    }

    #[Route('/templates', name: 'admin_page_builder_templates', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('@NowoPageBuilderKitBundle/admin/pages/templates.html.twig', [
            'templates'  => $this->templateService->list(),
            'csrf_token' => $this->csrfTokenManager->getToken(self::CSRF_TOKEN_ID)->getValue(),
            'pages'      => $this->pageRepository->findAllOrdered(),
        ]);
    }

    #[Route('/pages/{pageKey}/templates', name: 'admin_page_builder_templates_save', requirements: ['pageKey' => '[a-z0-9_-]+'], methods: ['POST'])]
    public function saveFromPage(string $pageKey, Request $request): Response
    {
        if (!$this->validateCsrf($request)) {
            return $this->error($request, 'invalid_csrf', Response::HTTP_FORBIDDEN);
        }

        $page = $this->pageRepository->findOneByPageKey($pageKey);
        if (!$page instanceof BuilderPage) {
            return $this->error($request, 'not_found', Response::HTTP_NOT_FOUND);
        }

        /** @var array<string, mixed> $payload */
        $payload     = json_decode($request->getContent(), true) ?? [];
        $templateKey = is_string($payload['templateKey'] ?? null)
            ? $payload['templateKey']
            : (string) $request->request->get('templateKey', '');
        $label = is_string($payload['label'] ?? null)
            ? $payload['label']
            : (string) $request->request->get('label', '');

        try {
            $template = $this->templateService->saveFromPage($page, $templateKey, $label);
        } catch (InvalidArgumentException $exception) {
            return $this->error($request, $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        // @igor-ignore - Request-scoped debug trace; ResetInterface clears between worker requests.
        $this->trace->addAdminAction('template_save', $pageKey);

        if ($this->wantsJson($request)) {
            return new JsonResponse([
                'ok'          => true,
                'templateKey' => $template->getTemplateKey(),
            ]);
        }

        $this->addFlash('success', 'admin.templates.saved');

        return $this->redirectToRoute('admin_page_builder_templates');
    }

    #[Route('/templates/{templateKey}/apply', name: 'admin_page_builder_templates_apply', requirements: ['templateKey' => '[a-z0-9_-]+'], methods: ['POST'])]
    public function apply(string $templateKey, Request $request): Response
    {
        if (!$this->validateCsrf($request)) {
            return $this->error($request, 'invalid_csrf', Response::HTTP_FORBIDDEN);
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];
        $pageKey = is_string($payload['pageKey'] ?? null)
            ? $payload['pageKey']
            : (string) $request->request->get('pageKey', '');
        $title = is_string($payload['title'] ?? null)
            ? $payload['title']
            : (string) $request->request->get('title', $pageKey);

        try {
            $page = $this->templateService->createPageFromTemplate(
                $templateKey,
                $pageKey,
                trim($title) !== '' ? $title : $pageKey,
                $this->builderLocales->getDefault(),
            );
        } catch (InvalidArgumentException $exception) {
            return $this->error($request, $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        // @igor-ignore - Request-scoped debug trace; ResetInterface clears between worker requests.
        $this->trace->addAdminAction('template_apply', $page->getPageKey());

        if ($this->wantsJson($request)) {
            return new JsonResponse(['ok' => true, 'pageKey' => $page->getPageKey()]);
        }

        $this->addFlash('success', 'admin.templates.applied');

        return $this->redirectToRoute('admin_page_builder_canvas', ['pageKey' => $page->getPageKey()]);
    }

    #[Route('/templates/{templateKey}/delete', name: 'admin_page_builder_templates_delete', requirements: ['templateKey' => '[a-z0-9_-]+'], methods: ['POST'])]
    public function delete(string $templateKey, Request $request): Response
    {
        if (!$this->validateCsrf($request)) {
            return $this->error($request, 'invalid_csrf', Response::HTTP_FORBIDDEN);
        }

        try {
            $this->templateService->delete($templateKey);
        } catch (InvalidArgumentException $exception) {
            return $this->error($request, $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        if ($this->wantsJson($request)) {
            return new JsonResponse(['ok' => true]);
        }

        $this->addFlash('success', 'admin.templates.deleted');

        return $this->redirectToRoute('admin_page_builder_templates');
    }

    private function validateCsrf(Request $request): bool
    {
        $token = (string) (
            $request->headers->get('X-CSRF-TOKEN')
            ?: $request->request->get('_csrf_token', '')
        );

        return $this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $token));
    }

    private function wantsJson(Request $request): bool
    {
        return $request->getPreferredFormat() === 'json'
            || $request->headers->get('Accept') === 'application/json';
    }

    private function error(Request $request, string $message, int $status): Response
    {
        if ($this->wantsJson($request)) {
            return new JsonResponse(['error' => $message], $status);
        }

        $this->addFlash('danger', $message);

        return $this->redirectToRoute('admin_page_builder_templates');
    }
}
