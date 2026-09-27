<?php

declare(strict_types=1);

namespace App\Controller;

use App\Demo\DemoPageSeeder;
use App\Demo\DemoUseCases;
use Nowo\PageBuilderKitBundle\Enum\PageStatus;
use Nowo\PageBuilderKitBundle\Service\PageRenderProvider;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class DemoController extends AbstractController
{
    public function __construct(
        private readonly PageRenderProvider $pageRenderProvider,
        private readonly DemoPageSeeder $demoPageSeeder,
    ) {
    }

    #[Route('/showcase', name: 'showcase')]
    public function showcase(Request $request): Response
    {
        $this->demoPageSeeder->ensureAll();
        $locale = $this->resolveLocale($request);

        return $this->render('demo/showcase.html.twig', [
            'locale'         => $locale,
            'use_cases'      => DemoUseCases::all(),
            'by_category'    => DemoUseCases::byCategory(),
            'seed_version'   => DemoUseCases::SEED_VERSION,
            'page_title'     => $locale === 'es' ? 'Casos de uso' : 'Use cases',
            'current_page_key' => 'showcase',
        ]);
    }

    #[Route('/', name: 'home')]
    public function home(Request $request): Response
    {
        return $this->renderDemoPage('home', $request);
    }

    #[Route('/compounds', name: 'compounds')]
    public function compounds(Request $request): Response
    {
        return $this->renderDemoPage('compounds', $request);
    }

    #[Route('/pricing', name: 'pricing')]
    public function pricing(Request $request): Response
    {
        return $this->renderDemoPage('pricing', $request);
    }

    #[Route('/about', name: 'about')]
    public function about(Request $request): Response
    {
        return $this->renderDemoPage('about', $request);
    }

    #[Route('/contact', name: 'contact')]
    public function contact(Request $request): Response
    {
        return $this->renderDemoPage('contact', $request);
    }

    #[Route('/blog', name: 'blog')]
    public function blog(Request $request): Response
    {
        return $this->renderDemoPage('blog', $request);
    }

    #[Route('/faq', name: 'faq')]
    public function faq(Request $request): Response
    {
        return $this->renderDemoPage('faq', $request);
    }

    #[Route('/portfolio', name: 'portfolio')]
    public function portfolio(Request $request): Response
    {
        return $this->renderDemoPage('portfolio', $request);
    }

    #[Route('/product', name: 'product')]
    public function product(Request $request): Response
    {
        return $this->renderDemoPage('product', $request);
    }

    #[Route('/newsletter', name: 'newsletter')]
    public function newsletter(Request $request): Response
    {
        return $this->renderDemoPage('newsletter', $request);
    }

    #[Route('/forms', name: 'forms')]
    public function forms(Request $request): Response
    {
        return $this->renderDemoPage('forms', $request);
    }

    #[Route('/legal', name: 'legal')]
    public function legal(Request $request): Response
    {
        return $this->renderDemoPage('legal', $request);
    }

    #[Route('/empty', name: 'empty')]
    public function emptyPage(Request $request): Response
    {
        return $this->renderDemoPage('empty', $request);
    }

    #[Route('/i18n', name: 'i18n')]
    public function i18n(Request $request): Response
    {
        return $this->renderDemoPage('i18n', $request);
    }

    #[Route('/classic', name: 'classic')]
    public function classic(Request $request): Response
    {
        return $this->renderDemoPage('classic', $request);
    }

    #[Route('/sections', name: 'sections')]
    public function sections(Request $request): Response
    {
        return $this->renderDemoPage('sections', $request);
    }

    #[Route('/sections-i18n', name: 'sections_i18n')]
    public function sectionsI18n(Request $request): Response
    {
        return $this->renderDemoPage('sections-i18n', $request);
    }

    #[Route('/twig', name: 'twig')]
    public function twig(Request $request): Response
    {
        return $this->renderDemoPage('twig', $request);
    }

    #[Route('/seo', name: 'seo')]
    public function seo(Request $request): Response
    {
        return $this->renderDemoPage('seo', $request);
    }

    #[Route('/draft', name: 'draft')]
    public function draft(Request $request): Response
    {
        return $this->renderDemoPage('draft', $request);
    }

    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('home');
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error'         => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('Intercepted by the firewall logout listener.');
    }

    private function renderDemoPage(string $pageKey, Request $request): Response
    {
        $case = DemoUseCases::byKey($pageKey);
        if ($case === null) {
            throw $this->createNotFoundException();
        }

        // Keep the whole matrix warm so /showcase and deep links always work.
        $this->demoPageSeeder->ensureAll();
        $locale = $this->resolveLocale($request);

        try {
            $tree = $this->pageRenderProvider->getRenderedTree($pageKey, $locale);
        } catch (RuntimeException) {
            $tree = [
                'pageKey'  => $pageKey,
                'locale'   => $locale,
                'status'   => $case['publish'] ? PageStatus::Published->value : PageStatus::Draft->value,
                'title'    => $locale === 'es' ? $case['title_es'] : $case['title_en'],
                'slug'     => $pageKey,
                'engine'   => $case['engine'] === 'classic' ? 'classic' : 'grapesjs',
                'html'     => '',
                'css'      => '',
                'sections' => [],
            ];
        }

        $fallbackTitle = $locale === 'es' ? $case['title_es'] : $case['title_en'];

        return $this->render('demo/index.html.twig', [
            'current_page_key' => $pageKey,
            'page_key'         => $pageKey,
            'page_title'       => ($tree['title'] ?? '') !== '' ? (string) $tree['title'] : $fallbackTitle,
            'page_tree'        => $tree,
            'demo_pages'       => DemoUseCases::all(),
            'use_case'         => $case,
            'seed_version'     => DemoUseCases::SEED_VERSION,
        ]);
    }

    private function resolveLocale(Request $request): string
    {
        $locale = (string) $request->query->get('_locale', $request->getLocale());
        if (!\in_array($locale, ['en', 'es'], true)) {
            $locale = 'en';
        }
        $request->setLocale($locale);

        return $locale;
    }
}
