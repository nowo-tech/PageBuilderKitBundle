<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Controller\Admin;

use Nowo\PageBuilderKitBundle\Locale\BuilderLocales;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRepository;
use Nowo\PageBuilderKitBundle\Service\DocumentNormalizer;
use Nowo\PageBuilderKitBundle\Service\GrapesJsFrontendConfig;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

use function sprintf;

final class PageCanvasController extends AbstractController
{
    public function __construct(
        private readonly BuilderPageRepository $pageRepository,
        private readonly BuilderLocales $builderLocales,
        private readonly DocumentNormalizer $documentNormalizer,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly GrapesJsFrontendConfig $grapesJsFrontendConfig,
    ) {
    }

    #[Route('/admin/page-builder/pages/{pageKey}/canvas', name: 'admin_page_builder_canvas', requirements: ['pageKey' => '[a-z0-9_-]+'], methods: ['GET'])]
    public function canvas(string $pageKey): Response
    {
        $page = $this->pageRepository->findOneByPageKey($pageKey);
        if ($page === null) {
            throw $this->createNotFoundException(sprintf('Unknown page "%s".', $pageKey));
        }

        $document  = $page->getDocument();
        $structure = $this->documentNormalizer->normalize(
            $document?->getStructure() ?? $this->documentNormalizer->emptyStructure(),
        );
        $grapesConfig = $this->grapesJsFrontendConfig->toArray();
        if (!empty($grapesConfig['assetsUploadEnabled'])) {
            $grapesConfig['uploadUrl'] = $this->generateUrl('admin_page_builder_asset_upload');
            $grapesConfig['assetCsrf'] = $this->csrfTokenManager
                ->getToken(PageAssetUploadController::CSRF_TOKEN_ID)
                ->getValue();
        }

        return $this->render('@NowoPageBuilderKitBundle/admin/pages/canvas.html.twig', [
            'page'                 => $page,
            'page_key'             => $pageKey,
            'locales'              => $this->builderLocales->getAll(),
            'default_locale'       => $this->builderLocales->getDefault(),
            'structure'            => $structure,
            'grapesjs_config'      => $grapesConfig,
            'grapesjs_cdn_version' => $grapesConfig['cdnVersion'],
            'csrf_token'           => $this->csrfTokenManager->getToken('page_builder_document')->getValue(),
            'document_api_url'     => $this->generateUrl('admin_page_builder_document_get', ['pageKey' => $pageKey]),
            'document_save_url'    => $this->generateUrl('admin_page_builder_document_save', ['pageKey' => $pageKey]),
            'document_publish_url' => $this->generateUrl('admin_page_builder_document_publish', ['pageKey' => $pageKey]),
            'preview_url'          => $this->generateUrl('page_builder_public_render', ['pageKey' => $pageKey]),
        ]);
    }
}
