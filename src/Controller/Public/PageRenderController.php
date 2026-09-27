<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Controller\Public;

use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Enum\PageStatus;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRepository;
use Nowo\PageBuilderKitBundle\Service\PageRenderProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PageRenderController extends AbstractController
{
    public function __construct(
        private readonly BuilderPageRepository $pageRepository,
        private readonly PageRenderProvider $pageRenderProvider,
    ) {
    }

    #[Route('/p/{pageKey}', name: 'page_builder_public_render', requirements: ['pageKey' => '[a-z0-9_-]+'], methods: ['GET'])]
    public function renderPage(string $pageKey, Request $request): Response
    {
        $page = $this->pageRepository->findOneByPageKey($pageKey);
        if (!$page instanceof BuilderPage) {
            throw $this->createNotFoundException();
        }

        if ($page->getStatus() !== PageStatus::Published) {
            throw $this->createNotFoundException();
        }

        $locale = $request->getLocale();
        $tree   = $this->pageRenderProvider->getRenderedTree($pageKey, $locale);

        return $this->render('@NowoPageBuilderKitBundle/public/page.html.twig', [
            'page_tree' => $tree,
        ]);
    }
}
