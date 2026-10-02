<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Controller\Admin;

use InvalidArgumentException;
use Nowo\PageBuilderKitBundle\Debug\NullPageBuilderKitTrace;
use Nowo\PageBuilderKitBundle\Debug\PageBuilderKitTraceInterface;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Form\CsrfPostType;
use Nowo\PageBuilderKitBundle\Form\TemplateApplyType;
use Nowo\PageBuilderKitBundle\Form\TemplateImportType;
use Nowo\PageBuilderKitBundle\Form\TemplateSaveType;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocales;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRepository;
use Nowo\PageBuilderKitBundle\Service\PageTemplateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

use function array_key_exists;
use function count;
use function is_array;
use function is_string;
use function json_decode;
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
        $pages = $this->pageRepository->findAllOrdered();
        /** @var array<string, FormView> $saveForms */
        $saveForms = [];
        foreach ($pages as $page) {
            $key             = $page->getPageKey();
            $saveForms[$key] = $this->createForm(TemplateSaveType::class, null, [
                'action' => $this->generateUrl('admin_page_builder_templates_save', ['pageKey' => $key]),
                'method' => 'POST',
            ])->createView();
        }

        $templates = $this->templateService->list();
        /** @var array<string, FormView> $applyForms */
        $applyForms = [];
        /** @var array<string, FormView> $deleteForms */
        $deleteForms = [];
        foreach ($templates as $template) {
            $key              = $template->getTemplateKey();
            $applyForms[$key] = $this->createForm(TemplateApplyType::class, null, [
                'action' => $this->generateUrl('admin_page_builder_templates_apply', ['templateKey' => $key]),
                'method' => 'POST',
            ])->createView();
            $deleteForms[$key] = $this->createForm(CsrfPostType::class, null, [
                'action' => $this->generateUrl('admin_page_builder_templates_delete', ['templateKey' => $key]),
                'method' => 'POST',
            ])->createView();
        }

        return $this->render('@NowoPageBuilderKitBundle/admin/pages/templates.html.twig', [
            'templates'   => $templates,
            'pages'       => $pages,
            'save_forms'  => $saveForms,
            'import_form' => $this->createForm(TemplateImportType::class, null, [
                'action' => $this->generateUrl('admin_page_builder_templates_import'),
                'method' => 'POST',
            ])->createView(),
            'apply_forms'  => $applyForms,
            'delete_forms' => $deleteForms,
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

        $includeFieldSchema = array_key_exists('include_field_schema', $payload)
            ? (bool) $payload['include_field_schema']
            : ($request->request->has('include_field_schema')
                ? $request->request->getBoolean('include_field_schema')
                : (!$request->request->has('pageKey')));
        $includeFieldValues = array_key_exists('include_field_values', $payload)
            ? (bool) $payload['include_field_values']
            : $request->request->getBoolean('include_field_values', false);
        $includeSeo = array_key_exists('include_seo', $payload)
            ? (bool) $payload['include_seo']
            : $request->request->getBoolean('include_seo', false);

        try {
            $page = $this->templateService->createPageFromTemplate(
                $templateKey,
                $pageKey,
                trim($title) !== '' ? $title : $pageKey,
                $this->builderLocales->getDefault(),
                [
                    'include_field_schema' => $includeFieldSchema,
                    'include_field_values' => $includeFieldValues,
                    'include_seo'          => $includeSeo,
                ],
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

    #[Route('/templates/export', name: 'admin_page_builder_templates_export_all', methods: ['GET'])]
    public function exportAll(): JsonResponse
    {
        $payload = $this->templateService->exportAll();

        // @igor-ignore - Request-scoped debug trace; ResetInterface clears between worker requests.
        $this->trace->addAdminAction('templates_export_all', (string) count($payload['templates']));

        return new JsonResponse($payload);
    }

    #[Route('/templates/{templateKey}/export', name: 'admin_page_builder_templates_export', requirements: ['templateKey' => '[a-z0-9_-]+'], methods: ['GET'])]
    public function export(string $templateKey): JsonResponse
    {
        try {
            $payload = $this->templateService->export($templateKey);
        } catch (InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        // @igor-ignore - Request-scoped debug trace; ResetInterface clears between worker requests.
        $this->trace->addAdminAction('template_export', $templateKey);

        return new JsonResponse($payload);
    }

    #[Route('/templates/import', name: 'admin_page_builder_templates_import', methods: ['POST'])]
    public function import(Request $request): Response
    {
        if (!$this->validateCsrf($request)) {
            return $this->error($request, 'invalid_csrf', Response::HTTP_FORBIDDEN);
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];
        if ($payload === [] && $request->request->has('payload')) {
            $decoded = json_decode((string) $request->request->get('payload'), true);
            $payload = is_array($decoded) ? $decoded : [];
        }

        $overwrite = true;
        if (isset($payload['overwrite'])) {
            $overwrite = (bool) $payload['overwrite'];
            unset($payload['overwrite']);
        } elseif ($request->request->has('overwrite')) {
            $overwrite = (bool) $request->request->get('overwrite');
        }

        try {
            $keys = $this->templateService->import($payload, $overwrite);
        } catch (InvalidArgumentException $exception) {
            return $this->error($request, $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        // @igor-ignore - Request-scoped debug trace; ResetInterface clears between worker requests.
        $this->trace->addAdminAction('templates_import', (string) count($keys));

        if ($this->wantsJson($request)) {
            return new JsonResponse(['ok' => true, 'templateKeys' => $keys]);
        }

        $this->addFlash('success', 'admin.templates.imported');

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
