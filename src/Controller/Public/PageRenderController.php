<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Controller\Public;

use Nowo\PageBuilderKitBundle\Debug\NullPageBuilderKitTrace;
use Nowo\PageBuilderKitBundle\Debug\PageBuilderKitTraceInterface;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Enum\PageStatus;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRepository;
use Nowo\PageBuilderKitBundle\Security\PageBuilderKitAccessCheckerInterface;
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
        private readonly PageBuilderKitAccessCheckerInterface $accessChecker,
        private readonly PageBuilderKitTraceInterface $trace = new NullPageBuilderKitTrace(),
    ) {
    }

    #[Route('/p/{pageKey}', name: 'page_builder_public_render', requirements: ['pageKey' => '[a-z0-9_-]+'], methods: ['GET'])]
    public function renderPage(string $pageKey, Request $request): Response
    {
        $page = $this->pageRepository->findOneByPageKey($pageKey);
        if (!$page instanceof BuilderPage) {
            // @igor-ignore - Request-scoped debug trace; ResetInterface clears between worker requests.
            $this->trace->addPublicOutcome($pageKey, 'not_found_missing', $request->getLocale());

            throw $this->createNotFoundException();
        }

        $isPublished  = $page->getStatus() === PageStatus::Published;
        $draftPreview = !$isPublished && $this->accessChecker->canAccess();

        if (!$isPublished && !$draftPreview) {
            // @igor-ignore - Request-scoped debug trace; ResetInterface clears between worker requests.
            $this->trace->addPublicOutcome($pageKey, 'not_found_draft', $request->getLocale());

            throw $this->createNotFoundException();
        }

        $locale = $request->getLocale();
        $tree   = $this->pageRenderProvider->getRenderedTree($pageKey, $locale, [], $draftPreview);

        // @igor-ignore - Request-scoped debug trace; ResetInterface clears between worker requests.
        $this->trace->addPublicOutcome(
            $pageKey,
            $draftPreview ? 'draft_preview' : 'published',
            $locale,
        );

        return $this->render('@NowoPageBuilderKitBundle/public/page.html.twig', [
            'page_tree'     => $tree,
            'draft_preview' => $draftPreview,
        ]);
    }
}
