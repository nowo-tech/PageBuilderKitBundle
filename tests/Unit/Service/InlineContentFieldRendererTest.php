<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Service;

use Nowo\PageBuilderKitBundle\Entity\BuilderDocument;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Enum\ContentFieldType;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocales;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRepositoryInterface;
use Nowo\PageBuilderKitBundle\Security\PageBuilderKitAccessCheckerInterface;
use Nowo\PageBuilderKitBundle\Service\ContentFieldsNormalizer;
use Nowo\PageBuilderKitBundle\Service\DocumentNormalizer;
use Nowo\PageBuilderKitBundle\Service\InlineContentFieldRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
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
use Twig\Environment;

#[CoversClass(InlineContentFieldRenderer::class)]
final class InlineContentFieldRendererTest extends TestCase
{
    /** @var list<array<string, mixed>> */
    private array $renderedContexts = [];

    #[Test]
    public function returnsEmptyStringForInvalidFieldKey(): void
    {
        $renderer = $this->renderer();

        self::assertSame('', $renderer->render('home', 'Bad Key'));
        self::assertSame('', $renderer->render('home', '1starts_digit'));
        self::assertSame([], $this->renderedContexts);
    }

    #[Test]
    public function rendersMissingPageWithDefaultValueAndNotEditable(): void
    {
        $renderer = $this->renderer(canContent: true);

        $html = $renderer->render('missing', 'hero_title', [
            'type'    => 'string',
            'label'   => 'Hero',
            'labels'  => ['es' => 'Título'],
            'locale'  => 'es',
            'default' => 'Fallback',
            'tag'     => 'h1',
            'class'   => 'title',
        ]);

        self::assertSame('rendered', $html);
        self::assertCount(1, $this->renderedContexts);
        $ctx = $this->renderedContexts[0];
        self::assertSame('missing', $ctx['page_key']);
        self::assertSame('hero_title', $ctx['field_key']);
        self::assertSame('Fallback', $ctx['value']);
        self::assertFalse($ctx['editable']);
        self::assertSame('', $ctx['save_url']);
        self::assertSame('', $ctx['csrf_token']);
        self::assertSame('h1', $ctx['tag']);
        self::assertSame('title', $ctx['attr_class']);
        self::assertSame('Título', $ctx['label']);
    }

    #[Test]
    public function rendersEditableFieldWhenPageExistsAndCanContent(): void
    {
        $page = $this->pageWithFields([
            ['key' => 'body', 'type' => 'html', 'label' => 'Body', 'labels' => ['en' => 'Body EN']],
            ['key' => 'show_cta', 'type' => 'bool', 'label' => 'CTA'],
        ], [
            'en' => ['body' => '<p>Hi</p>'],
        ]);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::once())
            ->method('generate')
            ->with('admin_page_builder_field_save', ['pageKey' => 'home', 'fieldKey' => 'body'])
            ->willReturn('/save/body');

        $renderer = $this->renderer(page: $page, canContent: true, urlGenerator: $urlGenerator);

        $renderer->render('home', 'body', [
            'locale'   => 'en',
            'label'    => 'Override',
            'labels'   => ['fr' => 'Corps'],
            'options'  => ['a', 'b'],
            'required' => true,
        ]);

        $ctx = $this->renderedContexts[0];
        self::assertSame(ContentFieldType::Html->value, $ctx['type']);
        self::assertSame('<p>Hi</p>', $ctx['value']);
        self::assertTrue($ctx['editable']);
        self::assertSame('/save/body', $ctx['save_url']);
        self::assertSame('csrf-token', $ctx['csrf_token']);
        self::assertTrue($ctx['include_assets']);
        self::assertTrue($ctx['is_html_output']);
        self::assertSame(['a', 'b'], $ctx['options']);
        self::assertTrue($ctx['required']);
        self::assertSame('Override', $ctx['label']);
    }

    #[Test]
    public function usesSchemaDefaultAndBoolEmptyWhenValueMissing(): void
    {
        $page = $this->pageWithFields([
            ['key' => 'show_cta', 'type' => 'bool', 'label' => 'CTA'],
            ['key' => 'title', 'type' => 'string', 'label' => 'Title', 'default' => 'From schema'],
        ], []);

        $renderer = $this->renderer(page: $page, canContent: false);

        $renderer->render('home', 'show_cta', ['locale' => 'es']);
        self::assertNotEmpty($this->renderedContexts);
        self::assertFalse($this->renderedContexts[0]['value']);
        self::assertFalse($this->renderedContexts[0]['editable']);
        self::assertSame('', $this->renderedContexts[0]['save_url']);

        $this->renderedContexts = [];
        $renderer->render('home', 'title', ['locale' => 'es']);
        self::assertNotEmpty($this->renderedContexts);
        self::assertSame('From schema', $this->renderedContexts[0]['value']);

        $this->renderedContexts = [];
        // resolveForLocale already supplies bool false from schema; options.default only applies when key absent
        $renderer->render('home', 'missing_key', ['locale' => 'es', 'default' => true, 'type' => 'bool']);
        self::assertNotEmpty($this->renderedContexts);
        self::assertTrue($this->renderedContexts[0]['value']);

        $this->renderedContexts = [];
        $renderer->render('home', 'ghost_bool', ['locale' => 'es', 'type' => 'bool']);
        self::assertNotEmpty($this->renderedContexts);
        self::assertFalse($this->renderedContexts[0]['value']);

        $this->renderedContexts = [];
        $renderer->render('home', 'ghost_str', ['locale' => 'es', 'type' => 'string']);
        self::assertNotEmpty($this->renderedContexts);
        self::assertSame('', $this->renderedContexts[0]['value']);
    }

    #[Test]
    public function respectsEditableFalseAndFallsBackToDefaultLocale(): void
    {
        $page = $this->pageWithFields([
            ['key' => 'hero_title', 'type' => 'string', 'label' => 'Hero'],
        ], [
            'es' => ['hero_title' => 'Hola'],
        ]);

        $renderer = $this->renderer(page: $page, canContent: true);

        $renderer->render('home', 'hero_title', ['editable' => false]);
        self::assertFalse($this->renderedContexts[0]['editable']);
        self::assertSame('Hola', $this->renderedContexts[0]['value']);
        self::assertSame('es', $this->renderedContexts[0]['locale']);
    }

    #[Test]
    public function marksAssetsOnlyOncePerRequest(): void
    {
        $page = $this->pageWithFields([
            ['key' => 'hero_title', 'type' => 'string', 'label' => 'Hero'],
        ], [
            'es' => ['hero_title' => 'Hola'],
        ]);

        $request = new Request();
        $stack   = new RequestStack();
        $stack->push($request);

        $renderer = $this->renderer(page: $page, canContent: true, requestStack: $stack);

        $renderer->render('home', 'hero_title');
        self::assertNotEmpty($this->renderedContexts);
        self::assertTrue($this->renderedContexts[0]['include_assets']);

        $this->renderedContexts = [];
        $renderer->render('home', 'hero_title');
        self::assertNotEmpty($this->renderedContexts);
        self::assertFalse($this->renderedContexts[0]['include_assets']);
    }

    #[Test]
    public function includesAssetsWhenNoCurrentRequest(): void
    {
        $page = $this->pageWithFields([
            ['key' => 'hero_title', 'type' => 'string', 'label' => 'Hero'],
        ], [
            'es' => ['hero_title' => 'Hola'],
        ]);

        $renderer = $this->renderer(
            page: $page,
            canContent: true,
            requestStack: new RequestStack(),
        );

        $renderer->render('home', 'hero_title');
        self::assertTrue($this->renderedContexts[0]['include_assets']);
        self::assertSame('/bundles/nowopagebuilderkit', $this->renderedContexts[0]['asset_base']);
    }

    #[Test]
    public function usesFieldFromSchemaWhenMatchingKeyAndTypeOptionOverride(): void
    {
        $page = $this->pageWithFields([
            ['key' => 'other', 'type' => 'string', 'label' => 'Other'],
            ['key' => 'hero_title', 'type' => 'text', 'label' => 'Hero', 'options' => ['x']],
        ], []);

        $renderer = $this->renderer(page: $page, canContent: true);

        $renderer->render('home', 'hero_title', ['type' => 'raw']);
        self::assertSame(ContentFieldType::Raw->value, $this->renderedContexts[0]['type']);
        self::assertSame('', $this->renderedContexts[0]['value']);
        self::assertSame(['x'], $this->renderedContexts[0]['options']);
        self::assertTrue($this->renderedContexts[0]['is_html_output']);
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @param array<string, array<string, mixed>> $fieldValues
     */
    private function pageWithFields(array $fields, array $fieldValues): BuilderPage
    {
        $page     = (new BuilderPage())->setPageKey('home');
        $document = (new BuilderDocument())->setPage($page)->setStructure([
            'fields'      => $fields,
            'fieldValues' => $fieldValues,
        ]);
        $page->setDocument($document);

        return $page;
    }

    private function renderer(
        ?BuilderPage $page = null,
        bool $canContent = false,
        ?UrlGeneratorInterface $urlGenerator = null,
        ?RequestStack $requestStack = null,
    ): InlineContentFieldRenderer {
        $this->renderedContexts = [];

        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturnCallback(function (string $template, array $context): string {
            self::assertSame('@NowoPageBuilderKitBundle/public/_editable_field.html.twig', $template);
            $this->renderedContexts[] = $context;

            return 'rendered';
        });

        $repo = $this->createStub(BuilderPageRepositoryInterface::class);
        $repo->method('findOneByPageKey')->willReturn($page);

        $access = $this->createStub(PageBuilderKitAccessCheckerInterface::class);
        $access->method('canContent')->willReturn($canContent);

        $csrfToken = new CsrfToken('page_builder_content', 'csrf-token');
        $csrf      = $this->createStub(CsrfTokenManagerInterface::class);
        $csrf->method('getToken')->willReturn($csrfToken);

        if ($urlGenerator instanceof UrlGeneratorInterface) {
            $urls = $urlGenerator;
        } else {
            $urls = $this->createMock(UrlGeneratorInterface::class);
            $urls->method('generate')->willReturn('/save');
        }

        $formView = $this->createStub(FormView::class);
        $form     = $this->createStub(FormInterface::class);
        $form->method('createView')->willReturn($formView);
        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        return new InlineContentFieldRenderer(
            $twig,
            $repo,
            new DocumentNormalizer(),
            new ContentFieldsNormalizer(),
            new BuilderLocales('es', ['es', 'en']),
            $access,
            $urls,
            $csrf,
            $requestStack ?? new RequestStack(),
            $formFactory,
            '/bundles/nowopagebuilderkit/',
        );
    }
}
