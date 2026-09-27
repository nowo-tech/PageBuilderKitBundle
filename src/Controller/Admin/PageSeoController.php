<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageTranslation;
use Nowo\PageBuilderKitBundle\Form\BuilderPageSeoType;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocales;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function in_array;
use function sprintf;

final class PageSeoController extends AbstractController
{
    public function __construct(
        private readonly BuilderPageRepository $pageRepository,
        private readonly BuilderLocales $builderLocales,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route(
        '/pages/{pageKey}/seo',
        name: 'admin_page_builder_seo',
        requirements: ['pageKey' => '[a-z0-9_-]+'],
        methods: ['GET', 'POST'],
    )]
    public function edit(string $pageKey, Request $request): Response
    {
        $page = $this->pageRepository->findOneByPageKey($pageKey);
        if (!$page instanceof BuilderPage) {
            throw $this->createNotFoundException(sprintf('Unknown page "%s".', $pageKey));
        }

        $locales = $this->builderLocales->getAll();
        $locale  = (string) $request->query->get('locale', $this->builderLocales->getDefault());
        if (!in_array($locale, $locales, true)) {
            $locale = $this->builderLocales->getDefault();
        }

        $translation = $page->getTranslation($locale);
        if (!$translation instanceof BuilderPageTranslation) {
            $translation = (new BuilderPageTranslation())
                ->setLocale($locale)
                ->setTitle($pageKey)
                ->setSlug($pageKey);
            $page->addTranslation($translation);
        }

        $form = $this->createForm(BuilderPageSeoType::class, $translation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'admin.seo.saved');

            return $this->redirectToRoute('admin_page_builder_seo', [
                'pageKey' => $pageKey,
                'locale'  => $locale,
            ]);
        }

        return $this->render('@NowoPageBuilderKitBundle/admin/pages/seo.html.twig', [
            'page'     => $page,
            'page_key' => $pageKey,
            'locales'  => $locales,
            'locale'   => $locale,
            'form'     => $form,
        ]);
    }
}
