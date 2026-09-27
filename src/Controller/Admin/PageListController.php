<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Controller\Admin;

use InvalidArgumentException;
use Nowo\PageBuilderKitBundle\Form\BuilderPageCreateType;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocales;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRepository;
use Nowo\PageBuilderKitBundle\Service\DocumentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PageListController extends AbstractController
{
    public function __construct(
        private readonly BuilderPageRepository $pageRepository,
        private readonly DocumentService $documentService,
        private readonly BuilderLocales $builderLocales,
    ) {
    }

    #[Route('/admin/page-builder/pages', name: 'admin_page_builder_list', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $createForm = $this->createForm(BuilderPageCreateType::class);
        $createForm->handleRequest($request);

        if ($createForm->isSubmitted() && $createForm->isValid()) {
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

        return $this->render('@NowoPageBuilderKitBundle/admin/pages/index.html.twig', [
            'pages'       => $this->pageRepository->findAllOrdered(),
            'create_form' => $createForm,
        ]);
    }
}
