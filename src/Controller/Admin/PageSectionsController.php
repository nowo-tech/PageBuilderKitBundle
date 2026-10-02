<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Controller\Admin;

use InvalidArgumentException;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Form\CsrfPostType;
use Nowo\PageBuilderKitBundle\Form\SectionsEditType;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocales;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRepository;
use Nowo\PageBuilderKitBundle\Service\DocumentNormalizer;
use Nowo\PageBuilderKitBundle\Service\DocumentService;
use Nowo\PageBuilderKitBundle\Widget\WidgetTypeRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function in_array;
use function is_array;
use function is_numeric;
use function is_string;
use function sprintf;

/**
 * Classic schema v1: edit widget props section-by-section per locale.
 */
final class PageSectionsController extends AbstractController
{
    public function __construct(
        private readonly BuilderPageRepository $pageRepository,
        private readonly DocumentService $documentService,
        private readonly DocumentNormalizer $documentNormalizer,
        private readonly BuilderLocales $builderLocales,
        private readonly WidgetTypeRegistry $widgetTypeRegistry,
    ) {
    }

    #[Route(
        '/pages/{pageKey}/sections',
        name: 'admin_page_builder_sections',
        requirements: ['pageKey' => '[a-z0-9_-]+'],
        methods: ['GET', 'POST'],
    )]
    public function edit(string $pageKey, Request $request): Response
    {
        $page = $this->pageRepository->findOneByPageKey($pageKey);
        if (!$page instanceof BuilderPage) {
            throw $this->createNotFoundException(sprintf('Unknown page "%s".', $pageKey));
        }

        $structure = $this->documentNormalizer->normalize($page->getDocument()?->getStructure() ?? []);
        if ($this->documentNormalizer->isGrapesStructure($structure)) {
            $this->addFlash('error', 'admin.sections.grapes_only');

            return $this->redirectToRoute('admin_page_builder_canvas', ['pageKey' => $pageKey]);
        }

        $locales = $this->builderLocales->getAll();
        $locale  = (string) $request->query->get('locale', $this->builderLocales->getDefault());
        if (!in_array($locale, $locales, true)) {
            $locale = $this->builderLocales->getDefault();
        }

        $propsByLocale = $this->collectPropsByLocale($page, $locales);
        $sectionsTree  = $this->buildSectionsTree($structure, $propsByLocale[$locale] ?? []);

        $form = $this->createForm(SectionsEditType::class, null, [
            'sections_tree' => $sectionsTree,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{widgets?: array<string, array<string, mixed>>} $data */
            $data                   = $form->getData() ?? [];
            $submitted              = is_array($data['widgets'] ?? null) ? $data['widgets'] : [];
            $propsByLocale[$locale] = $this->normalizeSubmittedProps($submitted);

            try {
                $this->documentService->saveDocument($page, $structure, $propsByLocale);
                $this->addFlash('success', 'admin.sections.saved');
            } catch (InvalidArgumentException $exception) {
                $this->addFlash('error', $exception->getMessage());
            }

            return $this->redirectToRoute('admin_page_builder_sections', [
                'pageKey' => $pageKey,
                'locale'  => $locale,
            ]);
        }

        $redirect = $this->generateUrl('admin_page_builder_sections', [
            'pageKey' => $pageKey,
            'locale'  => $locale,
        ]);

        return $this->render('@NowoPageBuilderKitBundle/admin/pages/sections.html.twig', [
            'page'          => $page,
            'page_key'      => $pageKey,
            'locales'       => $locales,
            'locale'        => $locale,
            'sections_tree' => $sectionsTree,
            'is_classic'    => true,
            'form'          => $form->createView(),
            'publish_form'  => $this->createForm(CsrfPostType::class, ['_redirect' => $redirect], [
                'action' => $this->generateUrl('admin_page_builder_document_publish', ['pageKey' => $pageKey]),
                'method' => 'POST',
            ])->createView(),
            'unpublish_form' => $this->createForm(CsrfPostType::class, ['_redirect' => $redirect], [
                'action' => $this->generateUrl('admin_page_builder_document_unpublish', ['pageKey' => $pageKey]),
                'method' => 'POST',
            ])->createView(),
        ]);
    }

    /**
     * @param list<string> $locales
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function collectPropsByLocale(BuilderPage $page, array $locales): array
    {
        $out = [];
        foreach ($locales as $locale) {
            $out[$locale] = $page->getDocument()?->getLocaleDocument($locale)?->getWidgetProps() ?? [];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $structure
     * @param array<string, array<string, mixed>> $localeProps
     *
     * @return list<array{id: string, label: string, columns: list<array{id: string, width: int, widgets: list<array<string, mixed>>}>}>
     */
    private function buildSectionsTree(array $structure, array $localeProps): array
    {
        $tree  = [];
        $index = 1;
        foreach ($structure['sections'] ?? [] as $section) {
            if (!is_array($section)) {
                continue;
            }
            $sectionId = is_string($section['id'] ?? null) ? $section['id'] : 'section-' . $index;
            $columns   = [];
            foreach ($section['columns'] ?? [] as $column) {
                if (!is_array($column)) {
                    continue;
                }
                $widgets = [];
                foreach ($column['widgets'] ?? [] as $widget) {
                    $this->appendWidgetNodes($widgets, $widget, $localeProps, 0);
                }
                $columns[] = [
                    'id'      => is_string($column['id'] ?? null) ? $column['id'] : '',
                    'width'   => (int) ($column['settings']['width'] ?? 12),
                    'widgets' => $widgets,
                ];
            }

            $cssId  = is_string($section['settings']['cssId'] ?? null) ? $section['settings']['cssId'] : '';
            $tree[] = [
                'id'      => $sectionId,
                'label'   => $cssId !== '' ? sprintf('Section %d · #%s', $index, $cssId) : sprintf('Section %d', $index),
                'columns' => $columns,
            ];
            ++$index;
        }

        return $tree;
    }

    /**
     * @param list<array<string, mixed>> $out
     * @param array<string, array<string, mixed>> $localeProps
     */
    private function appendWidgetNodes(array &$out, mixed $widget, array $localeProps, int $depth): void
    {
        if (!is_array($widget)) {
            return;
        }
        $id   = is_string($widget['id'] ?? null) ? $widget['id'] : '';
        $type = is_string($widget['type'] ?? null) ? $widget['type'] : '';
        if ($id === '' || $type === '' || !$this->widgetTypeRegistry->has($type)) {
            return;
        }

        $widgetType = $this->widgetTypeRegistry->get($type);
        $defaults   = $widgetType->defaultProps();
        $stored     = is_array($localeProps[$id] ?? null) ? $localeProps[$id] : [];
        $props      = array_merge($defaults, $stored);

        $out[] = [
            'id'       => $id,
            'type'     => $type,
            'depth'    => $depth,
            'label'    => $widgetType->getLabelKey(),
            'props'    => $props,
            'fields'   => array_keys($defaults),
            'children' => [],
        ];

        if ($widgetType->allowsChildren()) {
            foreach ($widget['children'] ?? [] as $child) {
                $this->appendWidgetNodes($out, $child, $localeProps, $depth + 1);
            }
        }
    }

    /**
     * @param array<int|string, mixed> $submitted
     *
     * @return array<string, array<string, mixed>>
     */
    private function normalizeSubmittedProps(array $submitted): array
    {
        $out = [];
        foreach ($submitted as $widgetId => $fields) {
            if (!is_string($widgetId) || $widgetId === '' || !is_array($fields)) {
                continue;
            }
            $clean = [];
            foreach ($fields as $key => $value) {
                if (!is_string($key)) {
                    continue;
                }
                $clean[$key] = is_string($value) || is_numeric($value) ? $value : (string) $value;
            }
            $out[$widgetId] = $clean;
        }

        return $out;
    }
}
