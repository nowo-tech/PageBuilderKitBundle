<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Controller\Admin;

use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocales;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRepository;
use Nowo\PageBuilderKitBundle\Service\ContentFieldsNormalizer;
use Nowo\PageBuilderKitBundle\Service\DocumentNormalizer;
use Nowo\PageBuilderKitBundle\Service\GrapesJsFrontendConfig;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

use function is_array;
use function sprintf;

final class PageCanvasController extends AbstractController
{
    public function __construct(
        private readonly BuilderPageRepository $pageRepository,
        private readonly BuilderLocales $builderLocales,
        private readonly DocumentNormalizer $documentNormalizer,
        private readonly ContentFieldsNormalizer $contentFieldsNormalizer,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly GrapesJsFrontendConfig $grapesJsFrontendConfig,
    ) {
    }

    #[Route('/pages/{pageKey}/canvas', name: 'admin_page_builder_canvas', requirements: ['pageKey' => '[a-z0-9_-]+'], methods: ['GET'])]
    public function canvas(string $pageKey): Response
    {
        $page = $this->pageRepository->findOneByPageKey($pageKey);
        if (!$page instanceof BuilderPage) {
            throw $this->createNotFoundException(sprintf('Unknown page "%s".', $pageKey));
        }

        $document  = $page->getDocument();
        $structure = $this->documentNormalizer->normalize(
            $document?->getStructure() ?? $this->documentNormalizer->emptyStructure(),
        );
        $grapesConfig = $this->grapesJsFrontendConfig->toArray();
        if (!empty($grapesConfig['assetsUploadEnabled'])) {
            $grapesConfig['uploadUrl']        = $this->generateUrl('admin_page_builder_asset_upload');
            $grapesConfig['assetsLibraryUrl'] = $this->generateUrl('admin_page_builder_assets_list');
            $grapesConfig['assetCsrf']        = $this->csrfTokenManager
                ->getToken(PageAssetUploadController::CSRF_TOKEN_ID)
                ->getValue();
        }

        $schema                        = $this->contentFieldsNormalizer->normalizeSchema($structure['fields'] ?? []);
        $grapesConfig['contentFields'] = $this->buildContentFieldCatalog($schema);

        return $this->render('@NowoPageBuilderKitBundle/admin/pages/canvas.html.twig', [
            'page'                   => $page,
            'page_key'               => $pageKey,
            'locales'                => $this->builderLocales->getAll(),
            'default_locale'         => $this->builderLocales->getDefault(),
            'structure'              => $structure,
            'grapesjs_config'        => $grapesConfig,
            'grapesjs_cdn_version'   => $grapesConfig['cdnVersion'],
            'csrf_token'             => $this->csrfTokenManager->getToken('page_builder_document')->getValue(),
            'document_api_url'       => $this->generateUrl('admin_page_builder_document_get', ['pageKey' => $pageKey]),
            'document_save_url'      => $this->generateUrl('admin_page_builder_document_save', ['pageKey' => $pageKey]),
            'document_publish_url'   => $this->generateUrl('admin_page_builder_document_publish', ['pageKey' => $pageKey]),
            'document_unpublish_url' => $this->generateUrl('admin_page_builder_document_unpublish', ['pageKey' => $pageKey]),
            'page_status'            => $page->getStatus()->value,
            'preview_url'            => $this->generateUrl('page_builder_public_render', ['pageKey' => $pageKey]),
        ]);
    }

    /**
     * Flatten schema into Grapes blocks + Dynamic tag trait options.
     *
     * @param list<array<string, mixed>> $schema
     *
     * @return list<array{key: string, type: string, label: string, sample: string, twigSample: string, bind: string}>
     */
    private function buildContentFieldCatalog(array $schema, string $pathPrefix = '', string $labelPrefix = ''): array
    {
        $out = [];
        foreach ($schema as $field) {
            $key   = (string) ($field['key'] ?? '');
            $type  = (string) ($field['type'] ?? 'string');
            $label = (string) ($field['label'] ?? $key);
            if ($key === '') {
                continue;
            }
            $path         = $pathPrefix === '' ? $key : $pathPrefix . '.' . $key;
            $displayLabel = $labelPrefix === '' ? $label : $labelPrefix . ' › ' . $label;
            $slot         = '[[fields.' . $path . ']]';
            $twig         = '{{ fields.' . $path . ' }}';

            if ($type === 'repeater') {
                $firstSub = is_array($field['fields'][0] ?? null) ? (string) ($field['fields'][0]['key'] ?? 'title') : 'title';
                $sample   = '{% for row in fields.' . $path . ' %}<div>{{ row.' . $firstSub . ' }}</div>{% endfor %}';
                $out[]    = [
                    'key'        => $path,
                    'type'       => $type,
                    'label'      => $displayLabel,
                    'sample'     => $sample,
                    'twigSample' => $twig,
                    'bind'       => 'none',
                ];
                continue;
            }

            if ($type === 'group') {
                $out[] = [
                    'key'        => $path,
                    'type'       => $type,
                    'label'      => $displayLabel,
                    'sample'     => $slot,
                    'twigSample' => $twig,
                    'bind'       => 'none',
                ];
                $nestedRaw = $field['fields'] ?? [];
                if (is_array($nestedRaw) && $nestedRaw !== []) {
                    /** @var list<array<string, mixed>> $nested */
                    $nested = array_values($nestedRaw);
                    foreach ($this->buildContentFieldCatalog($nested, $path, $displayLabel) as $child) {
                        $out[] = $child;
                    }
                }
                continue;
            }

            $sample = $type === 'image' ? '<img src="' . $slot . '" alt="">' : $slot;
            $bind   = $type === 'image' ? 'src' : 'text';
            $out[]  = [
                'key'        => $path,
                'type'       => $type,
                'label'      => $displayLabel,
                'sample'     => $sample,
                'twigSample' => $twig,
                'bind'       => $bind,
            ];
        }

        return $out;
    }
}
