<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Controller\Admin;

use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageTranslation;
use Nowo\PageBuilderKitBundle\Form\BuilderPageSeoType;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocales;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRepository;
use Nowo\PageBuilderKitBundle\Service\DocumentService;
use Nowo\PageBuilderKitBundle\Service\PageStaleEditException;
use Nowo\PageBuilderKitBundle\Ui\PageBuilderKitPageFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function array_key_exists;
use function array_keys;
use function in_array;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function sprintf;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_UNICODE;

final class PageSeoController extends AbstractController
{
    use TranslatesFlashes;

    public function __construct(
        private readonly BuilderPageRepository $pageRepository,
        private readonly BuilderLocales $builderLocales,
        private readonly DocumentService $documentService,
        private readonly PageBuilderKitPageFactory $pageFactory,
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
            $translation = new BuilderPageTranslation()
                ->setLocale($locale)
                ->setTitle($pageKey)
                ->setSlug($pageKey)
                ->setPage($page);
            // Not added to the page's collection: the unit of DocumentService::saveTranslation() adds it when the form is saved.
        }

        // 1.18 (REQ-RUNTIME-003 rule 4): the values the form shows, posted back as `originals`.
        $shown = $this->shownValues($translation);
        $form  = $this->createForm(BuilderPageSeoType::class, $translation, ['originals' => json_encode($shown, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // 1.14: the bundle entity manager (not the autowired default) + PageMetadataSavedEvent after commit.
            // 1.18: field by field under the page lock; a field another editor changed to another value refuses the save.
            try {
                $this->documentService->saveTranslation($page, $translation, $this->postedOriginals($form->get('originals')->getData()));
                $this->addFlash('success', 'admin.seo.saved');
            } catch (PageStaleEditException) {
                $this->addFlash('danger', 'admin.stale_edit');
            }

            return $this->redirectToRoute('admin_page_builder_seo', [
                'pageKey' => $pageKey,
                'locale'  => $locale,
            ]);
        }

        $formView = $form->createView();

        return $this->render($this->pageFactory->template('seo'), $this->pageFactory->context([
            'page'         => $this->pageFactory->seo($page, $formView),
            'builder_page' => $page,
            'page_key'     => $pageKey,
            'locales'      => $locales,
            'locale'       => $locale,
            'form'         => $formView,
        ]), new Response(null, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /**
     * @return array<string, string|null>
     */
    private function shownValues(BuilderPageTranslation $translation): array
    {
        return [
            'title'           => $translation->getTitle(),
            'slug'            => $translation->getSlug(),
            'metaTitle'       => $translation->getMetaTitle(),
            'metaDescription' => $translation->getMetaDescription(),
            'ogTitle'         => $translation->getOgTitle(),
            'ogDescription'   => $translation->getOgDescription(),
            'ogImage'         => $translation->getOgImage(),
            'canonicalUrl'    => $translation->getCanonicalUrl(),
            'robots'          => $translation->getRobots(),
        ];
    }

    /**
     * The shown values the form posted back; empty (every field counts as changed) when missing or not what was rendered.
     *
     * @return array<string, string|null>
     */
    private function postedOriginals(mixed $raw): array
    {
        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach (array_keys($this->shownValues(new BuilderPageTranslation())) as $name) {
            if (array_key_exists($name, $decoded) && (is_string($decoded[$name]) || $decoded[$name] === null)) {
                $out[$name] = $decoded[$name];
            }
        }

        return $out;
    }
}
