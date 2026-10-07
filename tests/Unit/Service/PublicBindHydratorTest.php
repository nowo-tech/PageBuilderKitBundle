<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Service;

use Nowo\PageBuilderKitBundle\Content\ContentFieldDefinitionProviderInterface;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocales;
use Nowo\PageBuilderKitBundle\Security\PageBuilderKitAccessCheckerInterface;
use Nowo\PageBuilderKitBundle\Service\PublicBindHydrator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final class PublicBindHydratorTest extends TestCase
{
    #[Test]
    public function visitorsKeepHtmlWithoutRendering(): void
    {
        $html = '<p><span data-pbk-bind="hero_title">Hola</span></p>';

        $twig = $this->createMock(Environment::class);
        $twig->expects(self::never())->method('render');

        self::assertSame($html, $this->hydrator($twig, false)->hydrate($html, 'home'));
    }

    #[Test]
    public function htmlWithoutBindsIsReturnedUntouched(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects(self::never())->method('render');

        $hydrator = $this->hydrator($twig, true);

        self::assertSame('', $hydrator->hydrate('', 'home'));
        self::assertSame('<p>x</p>', $hydrator->hydrate('<p>x</p>', 'home'));
    }

    #[Test]
    public function editorsGetFieldTemplatePerSlotWithAssetsOnce(): void
    {
        $html = '<span data-pbk-bind="hero_title">Hola</span><span data-pbk-bind="hero_body" class="x">Body</span>';

        $form = $this->createMock(FormInterface::class);
        $form->method('createView')->willReturn(new FormView());
        $forms = $this->createMock(FormFactoryInterface::class);
        $forms->expects(self::once())->method('create')->willReturn($form);

        $contexts = [];
        $twig     = $this->createMock(Environment::class);
        $twig->expects(self::exactly(2))
            ->method('render')
            ->willReturnCallback(static function (string $template, array $ctx) use (&$contexts): string {
                self::assertSame('@NowoPageBuilderKitBundle/public/_editable_field.html.twig', $template);
                $contexts[$ctx['field_key']] = $ctx;

                return 'F:' . $ctx['field_key'] . ':' . $ctx['value'];
            });

        $stack = new RequestStack();
        $stack->push(Request::create('/'));

        $out = $this->hydrator($twig, true, $forms, $stack, [
            ['key' => 'hero_title', 'type' => 'string', 'label' => 'field.hero.title'],
            ['key' => 'hero_body', 'type' => 'html', 'label' => 'field.hero.body'],
        ])->hydrate($html, 'home');

        self::assertSame('F:hero_title:HolaF:hero_body:Body', $out);
        self::assertSame('home', $contexts['hero_title']['page_key']);
        self::assertSame('t:field.hero.title', $contexts['hero_title']['label']);
        self::assertSame('string', $contexts['hero_title']['type']);
        self::assertFalse($contexts['hero_title']['is_html_output']);
        self::assertTrue($contexts['hero_title']['include_assets']);
        self::assertInstanceOf(FormView::class, $contexts['hero_title']['modal_form']);
        self::assertSame('html', $contexts['hero_body']['type']);
        self::assertTrue($contexts['hero_body']['is_html_output']);
        self::assertFalse($contexts['hero_body']['include_assets']);
        self::assertNull($contexts['hero_body']['modal_form']);
        self::assertSame('/save', $contexts['hero_title']['save_url']);
        self::assertSame('csrf', $contexts['hero_title']['csrf_token']);
        self::assertSame('/assets', $contexts['hero_title']['asset_base']);
        self::assertTrue($contexts['hero_title']['editable']);
    }

    #[Test]
    public function unknownFieldsFallBackToStringTypeAndKeyLabel(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->willReturnCallback(static function (string $template, array $ctx): string {
                self::assertSame('string', $ctx['type']);
                self::assertSame('t:mystery', $ctx['label']);
                self::assertSame('en', $ctx['locale']);

                return 'OK';
            });

        $form = $this->createMock(FormInterface::class);
        $form->method('createView')->willReturn(new FormView());
        $forms = $this->createMock(FormFactoryInterface::class);
        $forms->method('create')->willReturn($form);

        // No request: locale falls back to BuilderLocales default, assets always included.
        self::assertSame('OK', $this->hydrator($twig, true, $forms)->hydrate('<span data-pbk-bind="mystery">v</span>', 'home'));
    }

    /**
     * @param list<array{key: string, type: string, label: string}> $definitions
     */
    private function hydrator(
        Environment $twig,
        bool $canContent,
        ?FormFactoryInterface $forms = null,
        ?RequestStack $stack = null,
        array $definitions = [],
    ): PublicBindHydrator {
        $access = $this->createMock(PageBuilderKitAccessCheckerInterface::class);
        $access->method('canContent')->willReturn($canContent);

        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('/save');

        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('getToken')->willReturn(new CsrfToken('page_builder_content', 'csrf'));

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => 't:' . $id);

        $provider = $this->createMock(ContentFieldDefinitionProviderInterface::class);
        $provider->method('definitions')->willReturn($definitions);

        return new PublicBindHydrator(
            $twig,
            $access,
            $urls,
            $csrf,
            $stack ?? new RequestStack(),
            $forms ?? $this->createMock(FormFactoryInterface::class),
            $translator,
            $provider,
            new BuilderLocales('en', ['en']),
            '/assets/',
        );
    }
}
