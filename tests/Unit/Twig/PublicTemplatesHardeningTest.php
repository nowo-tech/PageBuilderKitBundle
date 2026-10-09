<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Twig;

use Nowo\PageBuilderKitBundle\Html\PublicHtmlHardener;
use Nowo\PageBuilderKitBundle\Security\PageBuilderKitAccessCheckerInterface;
use Nowo\PageBuilderKitBundle\Service\InlineContentFieldRendererInterface;
use Nowo\PageBuilderKitBundle\Service\PageRenderProviderInterface;
use Nowo\PageBuilderKitBundle\Twig\PageBuilderKitExtension;
use Nowo\PageBuilderKitBundle\Widget\WidgetTypeRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\AppVariable;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

use function file_get_contents;
use function glob;
use function str_contains;

/**
 * Public templates: editor HTML/CSS goes through |pbk_harden_html / |pbk_harden_css, inline <script>/<style>
 * carry the request `csp_nonce` attribute and no template uses inline event handlers.
 */
#[CoversClass(PageBuilderKitExtension::class)]
#[CoversClass(PublicHtmlHardener::class)]
final class PublicTemplatesHardeningTest extends TestCase
{
    private const string VIEWS = __DIR__ . '/../../../src/Resources/views';

    #[Test]
    public function extensionExposesHardeningFilters(): void
    {
        $names = [];
        foreach ($this->extension()->getFilters() as $filter) {
            self::assertInstanceOf(TwigFilter::class, $filter);
            $names[] = $filter->getName();
        }

        self::assertSame(['pbk_harden_html', 'pbk_harden_css'], $names);
    }

    #[Test]
    public function publicPageHardensHtmlAndCssAndCarriesNonce(): void
    {
        $request = new Request();
        $request->attributes->set('csp_nonce', 'n0nc3');

        $html = $this->twig($request)->render('@NowoPageBuilderKitBundle/public/page.html.twig', [
            'page_tree' => [
                'pageKey' => 'home',
                'title'   => 'Home',
                'engine'  => 'grapesjs',
                'locale'  => 'en',
                'seo'     => [],
                'css'     => '.a{color:red}</sty</stylele><script>alert(1)</script>',
                'html'    => '<p>ok</p><img src=x onerror=alert(1)><script>alert(2)</script>',
            ],
        ]);

        self::assertStringContainsString('<style id="pbk-grapes-css" nonce="n0nc3">', $html);
        self::assertStringContainsString('<p>ok</p><img src="x">', $html);
        self::assertStringNotContainsString('onerror', $html);
        self::assertStringNotContainsString('alert(2)', $html);
        self::assertStringNotContainsString('</sty</stylele>', $html);
        self::assertStringNotContainsString('<script', $html);
    }

    #[Test]
    public function publicPageRendersWithoutRequestOrNonce(): void
    {
        $html = $this->twig(null)->render('@NowoPageBuilderKitBundle/public/page.html.twig', [
            'page_tree' => ['pageKey' => 'home', 'title' => 'Home', 'engine' => 'grapesjs', 'css' => '.a{}', 'html' => '<p>x</p>'],
        ]);

        self::assertStringContainsString('<style id="pbk-grapes-css">.a{}</style>', $html);
        self::assertStringNotContainsString('nonce=', $html);
    }

    #[Test]
    public function classicHtmlAndTextWidgetsAreHardened(): void
    {
        $twig = $this->twig(null);
        foreach (['html', 'text'] as $widget) {
            $out = $twig->render('@NowoPageBuilderKitBundle/widgets/' . $widget . '.html.twig', [
                'settings' => ['html' => '<p onclick="x()">ok</p><svg/onload=alert(1)><a href="javascript&colon;alert(1)">y</a>'],
            ]);

            self::assertStringContainsString('<p>ok</p>', $out);
            self::assertStringNotContainsString('onload', $out);
            self::assertStringNotContainsString('onclick', $out);
            self::assertStringNotContainsString('javascript', $out);
        }
    }

    #[Test]
    public function templatesHaveNoInlineEventHandlersAndInlineTagsCarryNonce(): void
    {
        $files = [
            ...(glob(self::VIEWS . '/*/*.twig') ?: []),
            ...(glob(self::VIEWS . '/*/*/*.twig') ?: []),
        ];
        self::assertNotEmpty($files);

        foreach ($files as $file) {
            $source = (string) file_get_contents($file);
            self::assertDoesNotMatchRegularExpression('/\bon(submit|click|change|load|error|input)\s*[:=]/i', $source, $file);

            if (str_contains($file, '/Collector/')) {
                continue; // Web Profiler panel: Symfony handles its own CSP nonces.
            }
            self::assertDoesNotMatchRegularExpression('/<(script|style)(?![^>]*nonce=)[^>]*>/i', $source, $file . ': <script>/<style> without nonce');
        }
    }

    private function twig(?Request $request): Environment
    {
        $loader = new FilesystemLoader();
        $loader->addPath(self::VIEWS, 'NowoPageBuilderKitBundle');

        $twig = new Environment($loader, ['strict_variables' => false, 'autoescape' => 'html']);
        $twig->addExtension(new TranslationExtension());
        $twig->addExtension($this->extension());
        $twig->addFunction(new TwigFunction('asset', static fn (string $path): string => '/bundles/x/' . $path));
        $twig->addFunction(new TwigFunction('path', static fn (string $route): string => '/' . $route));

        $app   = new AppVariable();
        $stack = new RequestStack();
        if ($request instanceof Request) {
            $stack->push($request);
        }
        $app->setRequestStack($stack);
        $twig->addGlobal('app', $app);

        return $twig;
    }

    private function extension(): PageBuilderKitExtension
    {
        return new PageBuilderKitExtension(
            'layout.html.twig',
            'bootstrap',
            $this->createStub(PageRenderProviderInterface::class),
            new WidgetTypeRegistry([]),
            $this->createStub(PageBuilderKitAccessCheckerInterface::class),
            $this->createStub(InlineContentFieldRendererInterface::class),
        );
    }
}
