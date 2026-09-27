<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Service;

use Nowo\PageBuilderKitBundle\Service\GrapesDocumentSanitizer;
use Nowo\PageBuilderKitBundle\Service\GrapesTwigRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GrapesTwigRenderer::class)]
final class GrapesTwigRendererTest extends TestCase
{
    #[Test]
    public function interpolatesVariablesWithAutoescape(): void
    {
        $renderer = new GrapesTwigRenderer(true, false, new GrapesDocumentSanitizer());
        $result   = $renderer->render(
            '<h1>{{ title }}</h1><p>{{ locale }}</p>',
            ['title' => 'Hello <b>x</b>', 'locale' => 'es'],
        );

        self::assertTrue($result['twigApplied']);
        self::assertNull($result['twigError']);
        self::assertStringContainsString('Hello &lt;b&gt;x&lt;/b&gt;', $result['html']);
        self::assertStringContainsString('<p>es</p>', $result['html']);
    }

    #[Test]
    public function supportsConditionalsAndPageArray(): void
    {
        $renderer = new GrapesTwigRenderer();
        $result   = $renderer->render(
            '{% if locale == "es" %}Hola {{ page.title }}{% else %}Hi{% endif %}',
            [
                'locale' => 'es',
                'page'   => ['title' => 'Inicio'],
            ],
        );

        self::assertTrue($result['twigApplied']);
        self::assertStringContainsString('Hola Inicio', $result['html']);
    }

    #[Test]
    public function loopsOverHostProductLists(): void
    {
        $renderer = new GrapesTwigRenderer();
        $result   = $renderer->render(
            '<ul>{% for p in products %}<li>{{ p.name }} — {{ p.price }}</li>{% endfor %}</ul>',
            [
                'products' => [
                    ['name' => 'Starter', 'price' => '$19'],
                    ['name' => 'Pro', 'price' => '$49'],
                ],
            ],
        );

        self::assertTrue($result['twigApplied']);
        self::assertNull($result['twigError']);
        self::assertStringContainsString('Starter — $19', $result['html']);
        self::assertStringContainsString('Pro — $49', $result['html']);
    }

    #[Test]
    public function skipsWhenDisabled(): void
    {
        $renderer = new GrapesTwigRenderer(false);
        $result   = $renderer->render('<p>{{ title }}</p>', ['title' => 'X']);

        self::assertFalse($result['twigApplied']);
        self::assertStringContainsString('{{ title }}', $result['html']);
    }

    #[Test]
    public function returnsErrorOnForbiddenTagsWithoutCrashing(): void
    {
        $renderer = new GrapesTwigRenderer();
        $result   = $renderer->render('{% include "evil.html.twig" %}', []);

        self::assertFalse($result['twigApplied']);
        self::assertNotNull($result['twigError']);
    }

    #[Test]
    public function strictVariablesSurfaceTwigErrors(): void
    {
        $renderer = new GrapesTwigRenderer(true, true, new GrapesDocumentSanitizer());
        $result   = $renderer->render('<p>{{ missingVar }}</p>', []);

        self::assertFalse($result['twigApplied']);
        self::assertNotNull($result['twigError']);
        self::assertStringContainsString('{{ missingVar }}', $result['html']);
    }
}
