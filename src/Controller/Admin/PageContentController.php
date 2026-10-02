<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Controller\Admin;

use InvalidArgumentException;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Enum\ContentFieldType;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocales;
use Nowo\PageBuilderKitBundle\Media\AssetUploadHandler;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRepository;
use Nowo\PageBuilderKitBundle\Security\PageBuilderKitAccessGuard;
use Nowo\PageBuilderKitBundle\Service\ContentFieldsNormalizer;
use Nowo\PageBuilderKitBundle\Service\ContentFieldsService;
use Nowo\PageBuilderKitBundle\Service\DocumentNormalizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

use function array_filter;
use function array_map;
use function array_merge;
use function array_values;
use function explode;
use function in_array;
use function is_array;
use function is_string;
use function trim;

final class PageContentController extends AbstractController
{
    public function __construct(
        private readonly BuilderPageRepository $pageRepository,
        private readonly BuilderLocales $builderLocales,
        private readonly DocumentNormalizer $documentNormalizer,
        private readonly ContentFieldsNormalizer $contentFieldsNormalizer,
        private readonly ContentFieldsService $contentFieldsService,
        private readonly PageBuilderKitAccessGuard $accessGuard,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly AssetUploadHandler $assetUploadHandler,
    ) {
    }

    #[Route(
        '/pages/{pageKey}/content',
        name: 'admin_page_builder_content',
        requirements: ['pageKey' => '[a-z0-9_-]+'],
        methods: ['GET', 'POST'],
    )]
    public function edit(string $pageKey, Request $request): Response
    {
        $page = $this->pageRepository->findOneByPageKey($pageKey);
        if (!$page instanceof BuilderPage) {
            throw $this->createNotFoundException();
        }

        $structure = $this->documentNormalizer->normalize($page->getDocument()?->getStructure() ?? []);
        $schema    = $this->contentFieldsNormalizer->normalizeSchema($structure['fields'] ?? []);
        $values    = $this->contentFieldsNormalizer->normalizeValues($structure['fieldValues'] ?? [], $schema);
        $locales   = $this->builderLocales->getAll();
        $active    = is_string($request->query->get('locale'))
            ? $request->query->get('locale')
            : $this->builderLocales->getDefault();
        if (!in_array($active, $locales, true)) {
            $active = $this->builderLocales->getDefault();
        }

        if ($request->isMethod('POST') && $request->request->get('action') === 'save_values') {
            if (!$this->validCsrf($request)) {
                $this->addFlash('danger', 'admin.content.invalid_csrf');

                return $this->redirectToRoute('admin_page_builder_content', ['pageKey' => $pageKey, 'locale' => $active]);
            }

            /** @var array<string, mixed> $postedRaw */
            $postedRaw = $request->request->all('fieldValues');
            $merged    = $values;
            foreach ($postedRaw as $locale => $bag) {
                if (!is_string($locale) || !in_array($locale, $locales, true) || !is_array($bag)) {
                    continue;
                }
                /* @var array<string, mixed> $bag */
                $merged[$locale] = array_merge($merged[$locale] ?? [], $bag);
            }

            /** @var array<string, mixed> $postedLabels */
            $postedLabels  = $request->request->all('fieldLabels');
            $labelsChanged = false;
            foreach ($schema as $index => $field) {
                $labelText = null;
                if (is_array($postedLabels[$active] ?? null)
                    && is_string($postedLabels[$active][$field['key']] ?? null)) {
                    $labelText = trim((string) $postedLabels[$active][$field['key']]);
                }
                if ($labelText === null || $labelText === '') {
                    continue;
                }
                $schema[$index] = $this->contentFieldsNormalizer->mergeLabels(
                    $field,
                    ['labels' => [$active => $labelText], 'label' => $labelText],
                    $active,
                );
                $labelsChanged = true;
            }

            try {
                $this->contentFieldsService->saveValues($page, $merged, $labelsChanged ? $schema : null);
                $this->addFlash('success', 'admin.content.saved');
            } catch (InvalidArgumentException $exception) {
                $this->addFlash('danger', $exception->getMessage());
            }

            return $this->redirectToRoute('admin_page_builder_content', ['pageKey' => $pageKey, 'locale' => $active]);
        }

        $defaultLocale = $this->builderLocales->getDefault();
        $schemaView    = [];
        foreach ($schema as $field) {
            $field['display_label'] = $this->contentFieldsNormalizer->resolveLabel($field, $active, $defaultLocale);
            $schemaView[]           = $field;
        }

        $referencePages = [];
        foreach ($this->pageRepository->findAllOrdered() as $listed) {
            $referencePages[] = $listed->getPageKey();
        }

        $assetUploadEnabled = $this->assetUploadHandler->isEnabled();

        return $this->render('@NowoPageBuilderKitBundle/admin/pages/content.html.twig', [
            'page'                  => $page,
            'pageKey'               => $pageKey,
            'schema'                => $schemaView,
            'fieldValues'           => $values,
            'locales'               => $locales,
            'active_locale'         => $active,
            'field_types'           => ContentFieldType::values(),
            'can_edit_schema'       => $this->accessGuard->checker()->canLayout(),
            'can_edit_values'       => $this->accessGuard->checker()->canContent(),
            'reference_pages'       => $referencePages,
            'asset_upload_enabled'  => $assetUploadEnabled,
            'asset_library_enabled' => $assetUploadEnabled && $this->assetUploadHandler->supportsLibrary(),
            'asset_upload_url'      => $assetUploadEnabled
                ? $this->generateUrl('admin_page_builder_asset_upload')
                : '',
            'asset_library_url' => $assetUploadEnabled
                ? $this->generateUrl('admin_page_builder_assets_list')
                : '',
            'asset_upload_csrf' => $assetUploadEnabled
                ? $this->csrfTokenManager->getToken(PageAssetUploadController::CSRF_TOKEN_ID)->getValue()
                : '',
        ]);
    }

    #[Route(
        '/pages/{pageKey}/content/schema',
        name: 'admin_page_builder_content_schema',
        requirements: ['pageKey' => '[a-z0-9_-]+'],
        methods: ['POST'],
    )]
    public function saveSchema(string $pageKey, Request $request): RedirectResponse
    {
        $page = $this->pageRepository->findOneByPageKey($pageKey);
        if (!$page instanceof BuilderPage) {
            throw $this->createNotFoundException();
        }

        if (!$this->validCsrf($request)) {
            $this->addFlash('danger', 'admin.content.invalid_csrf');

            return $this->redirectToRoute('admin_page_builder_content', ['pageKey' => $pageKey]);
        }

        $structure = $this->documentNormalizer->normalize($page->getDocument()?->getStructure() ?? []);
        $schema    = $this->contentFieldsNormalizer->normalizeSchema($structure['fields'] ?? []);

        $action        = (string) $request->request->get('schema_action', '');
        $defaultLocale = $this->builderLocales->getDefault();
        if ($action === 'add') {
            $label    = trim((string) $request->request->get('label', ''));
            $type     = (string) $request->request->get('type', ContentFieldType::String->value);
            $fieldDef = [
                'key'      => (string) $request->request->get('key', ''),
                'type'     => $type,
                'label'    => $label,
                'labels'   => $label !== '' ? [$defaultLocale => $label] : [],
                'required' => $request->request->getBoolean('required'),
                'options'  => array_values(array_filter(array_map(
                    trim(...),
                    explode(',', (string) $request->request->get('options', '')),
                ))),
                'default'   => null,
                'fields'    => [],
                'min'       => null,
                'max'       => null,
                'reference' => 'page',
            ];

            if (in_array($type, [ContentFieldType::Repeater->value, ContentFieldType::Group->value], true)) {
                $fieldDef['fields'] = $this->contentFieldsNormalizer->parseSubfieldsSpec(
                    (string) $request->request->get('subfields', ''),
                );
                $fieldDef['options'] = [];
                if ($type === ContentFieldType::Repeater->value) {
                    $min             = trim((string) $request->request->get('min', ''));
                    $max             = trim((string) $request->request->get('max', ''));
                    $fieldDef['min'] = $min !== '' ? (int) $min : null;
                    $fieldDef['max'] = $max !== '' ? (int) $max : null;
                }
            }

            $schema[] = $fieldDef;
        } elseif ($action === 'remove') {
            $removeKey = (string) $request->request->get('key', '');
            $schema    = array_values(array_filter(
                $schema,
                static fn (array $field): bool => $field['key'] !== $removeKey,
            ));
        }

        try {
            $this->contentFieldsService->saveSchema($page, $schema);
            $this->addFlash('success', 'admin.content.schema_saved');
        } catch (InvalidArgumentException $exception) {
            $this->addFlash('danger', $exception->getMessage());
        }

        return $this->redirectToRoute('admin_page_builder_content', ['pageKey' => $pageKey]);
    }

    private function validCsrf(Request $request): bool
    {
        return $this->csrfTokenManager->isTokenValid(
            new CsrfToken('page_builder_content', (string) $request->request->get('_csrf_token', '')),
        );
    }
}
