<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Controller\Admin;

use InvalidArgumentException;
use Nowo\PageBuilderKitBundle\Form\BuilderPageCreateType;
use Nowo\PageBuilderKitBundle\Form\PageDuplicateType;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocales;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRepository;
use Nowo\PageBuilderKitBundle\Security\PageBuilderKitAccessGuard;
use Nowo\PageBuilderKitBundle\Service\DocumentService;
use Nowo\PageBuilderKitBundle\Ui\PageBuilderKitPageFactory;
use Nowo\UiKitBundle\View\Pagination;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PageListController extends AbstractController
{
    use TranslatesFlashes;

    /** Pages per admin list page (REQ-PERF-001: the list is never loaded unbounded). */
    public const int PAGE_SIZE = 50;

    public function __construct(
        private readonly BuilderPageRepository $pageRepository,
        private readonly DocumentService $documentService,
        private readonly BuilderLocales $builderLocales,
        private readonly PageBuilderKitAccessGuard $accessGuard,
        private readonly PageBuilderKitPageFactory $pageFactory,
    ) {
    }

    #[Route('/pages', name: 'admin_page_builder_list', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $createForm = $this->createForm(BuilderPageCreateType::class);
        $createForm->handleRequest($request);

        if ($createForm->isSubmitted() && $createForm->isValid()) {
            $this->accessGuard->assertLayout();

            /** @var array{pageKey: string, title: string} $data */
            $data = $createForm->getData();

            try {
                $page = $this->documentService->createPage(
                    $data['pageKey'],
                    $data['title'],
                    $this->builderLocales->getDefault(),
                );
            } catch (InvalidArgumentException $exception) {
                $this->addFlash('error', $exception->getMessage());

                return $this->redirectToRoute('admin_page_builder_list');
            }

            return $this->redirectToRoute('admin_page_builder_canvas', ['pageKey' => $page->getPageKey()]);
        }

        $total       = $this->pageRepository->countAll();
        $totalPages  = max(1, (int) ceil($total / self::PAGE_SIZE));
        $currentPage = min($totalPages, max(1, $request->query->getInt('page', 1)));
        $pages       = $this->pageRepository->findOrderedSlice(($currentPage - 1) * self::PAGE_SIZE, self::PAGE_SIZE);
        /** @var array<string, FormView> $duplicateForms */
        $duplicateForms = [];
        $engines        = [];
        foreach ($pages as $page) {
            $key                  = $page->getPageKey();
            $engines[$key]        = PageBuilderKitPageFactory::engine($page);
            $duplicateForms[$key] = $this->createForm(PageDuplicateType::class, [
                'pageKey' => $key . '-copy',
            ], [
                'action' => $this->generateUrl('admin_page_builder_document_duplicate', ['pageKey' => $key]),
                'method' => 'POST',
            ])->createView();
        }

        $pagination = Pagination::fromTotal($total, $currentPage, self::PAGE_SIZE, [self::PAGE_SIZE], self::PAGE_SIZE, 'admin_page_builder_list');

        return $this->render($this->pageFactory->template('index'), $this->pageFactory->context([
            'page'            => $this->pageFactory->index($pages, $pagination, $duplicateForms),
            'pages'           => $pages,
            'engines'         => $engines,
            'current_page'    => $currentPage,
            'total_pages'     => $totalPages,
            'create_form'     => $this->accessGuard->checker()->canLayout() ? $createForm->createView() : null,
            'duplicate_forms' => $duplicateForms,
        ]), new Response(null, $createForm->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
