<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Support;

use DateTimeImmutable;
use Nowo\PageBuilderKitBundle\Entity\BuilderDocument;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageRevision;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageTemplate;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageTranslation;
use Nowo\PageBuilderKitBundle\Enum\PageStatus;
use Nowo\PageBuilderKitBundle\Form\BuilderPageCreateType;
use Nowo\PageBuilderKitBundle\Form\BuilderPageSeoType;
use Nowo\PageBuilderKitBundle\Form\ContentSchemaAddType;
use Nowo\PageBuilderKitBundle\Form\ContentSchemaRemoveType;
use Nowo\PageBuilderKitBundle\Form\ContentValuesType;
use Nowo\PageBuilderKitBundle\Form\CsrfPostType;
use Nowo\PageBuilderKitBundle\Form\PageDuplicateType;
use Nowo\PageBuilderKitBundle\Form\RevisionCreateType;
use Nowo\PageBuilderKitBundle\Form\SectionsEditType;
use Nowo\PageBuilderKitBundle\Form\TemplateApplyType;
use Nowo\PageBuilderKitBundle\Form\TemplateImportType;
use Nowo\PageBuilderKitBundle\Form\TemplateSaveType;
use Nowo\PageBuilderKitBundle\Security\AllowAllPageBuilderKitAccessChecker;
use Nowo\PageBuilderKitBundle\Security\PageBuilderKitAccessCheckerInterface;
use Nowo\PageBuilderKitBundle\Service\DocumentDiff;
use Nowo\PageBuilderKitBundle\Ui\PageBuilderKitPageFactory;
use Nowo\PageBuilderKitBundle\Ui\PageBuilderKitPages;
use Nowo\UiKitBundle\View\Pagination;
use ReflectionProperty;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Validator\Validation;

use function http_build_query;

/**
 * Page contexts of the REQ-UI-003 page contract test: the view models come from {@see PageBuilderKitPageFactory} (the
 * service the controllers use), the FormViews from the real form types (CSRF extension), the other variables mirror
 * the controllers.
 */
final class PageFixtures
{
    /**
     * @return array<string, mixed>
     */
    public static function index(bool $empty = false, ?PageBuilderKitPageFactory $factory = null): array
    {
        $pages = $empty ? [] : [
            self::page(1, 'home', PageStatus::Published, 'grapesjs'),
            self::page(2, 'legacy', PageStatus::Draft, 'classic'),
        ];
        $duplicateForms = [];
        $engines        = [];
        foreach ($pages as $page) {
            $key                  = $page->getPageKey();
            $engines[$key]        = PageBuilderKitPageFactory::engine($page);
            $duplicateForms[$key] = self::forms()->create(PageDuplicateType::class, ['pageKey' => $key . '-copy'], [
                'action' => '/admin_page_builder_document_duplicate?pageKey=' . $key,
                'method' => 'POST',
            ])->createView();
        }
        $pagination = $empty
            ? Pagination::fromTotal(0, 1, 50, [50], 50, 'admin_page_builder_list')
            : Pagination::fromTotal(120, 1, 50, [50], 50, 'admin_page_builder_list');
        $factory ??= self::factory();

        return $factory->context([
            'page'            => $factory->index($pages, $pagination, $duplicateForms),
            'pages'           => $pages,
            'engines'         => $engines,
            'current_page'    => 1,
            'total_pages'     => $empty ? 1 : 3,
            'create_form'     => self::forms()->create(BuilderPageCreateType::class)->createView(),
            'duplicate_forms' => $duplicateForms,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function canvas(PageStatus $status = PageStatus::Draft, ?PageBuilderKitPageFactory $factory = null): array
    {
        $page = self::page(1, 'home', $status, 'grapesjs');
        $factory ??= self::factory();

        return $factory->context([
            'page'                   => $factory->canvas($page),
            'builder_page'           => $page,
            'page_key'               => 'home',
            'locales'                => ['en', 'es'],
            'default_locale'         => 'en',
            'structure'              => ['version' => 2, 'engine' => 'grapesjs', 'html' => '<p>Hi "there"</p>', 'css' => '', 'grapes' => [], 'localeContent' => []],
            'grapesjs_config'        => ['cdnVersion' => '0.22.9', 'pluginCdnBase' => 'https://esm.sh'],
            'grapesjs_cdn_version'   => '0.22.9',
            'csrf_token'             => 'REDACTED-TEST-document',
            'document_api_url'       => '/admin_page_builder_document_get?pageKey=home',
            'document_save_url'      => '/admin_page_builder_document_save?pageKey=home',
            'document_publish_url'   => '/admin_page_builder_document_publish?pageKey=home',
            'document_unpublish_url' => '/admin_page_builder_document_unpublish?pageKey=home',
            'page_status'            => $status->value,
            'preview_url'            => '/page_builder_public_render?pageKey=home',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function content(bool $withFields = true, bool $media = true, ?PageBuilderKitPageFactory $factory = null): array
    {
        $page   = self::page(1, 'fields', PageStatus::Published, 'grapesjs');
        $schema = $withFields ? [
            ['key' => 'hero_title', 'type' => 'string', 'label' => 'Hero title', 'display_label' => 'Hero title', 'labels' => ['en' => 'Hero title'], 'required' => true, 'options' => [], 'default' => null, 'fields' => []],
            ['key' => 'hero_image', 'type' => 'image', 'label' => 'Hero image', 'display_label' => 'Hero image', 'labels' => [], 'required' => false, 'options' => [], 'default' => null, 'fields' => []],
            ['key' => 'tone', 'type' => 'select', 'label' => 'Tone', 'display_label' => 'Tone', 'labels' => [], 'required' => false, 'options' => ['calm', 'bold'], 'default' => null, 'fields' => []],
            ['key' => 'related', 'type' => 'reference', 'label' => 'Related page', 'display_label' => 'Related page', 'labels' => [], 'required' => false, 'options' => [], 'default' => null, 'fields' => []],
            ['key' => 'faqs', 'type' => 'repeater', 'label' => 'FAQ', 'display_label' => 'FAQ', 'labels' => [], 'required' => false, 'options' => [], 'default' => null, 'fields' => [
                ['key' => 'question', 'type' => 'string', 'label' => 'Question'],
                ['key' => 'answer', 'type' => 'text', 'label' => 'Answer'],
            ]],
        ] : [];
        $values = ['en' => ['hero_title' => 'Welcome', 'hero_image' => 'https://example.com/hero.png', 'faqs' => [['question' => 'Why?', 'answer' => 'Because.']]]];

        $removeForms = [];
        foreach ($schema as $field) {
            $removeForms[$field['key']] = self::forms()->create(ContentSchemaRemoveType::class, ['schema_action' => 'remove', 'key' => $field['key']], [
                'action' => '/admin_page_builder_content_schema?pageKey=fields',
                'method' => 'POST',
            ])->createView();
        }
        $addForm    = self::forms()->create(ContentSchemaAddType::class, ['schema_action' => 'add'], ['action' => '/admin_page_builder_content_schema?pageKey=fields', 'method' => 'POST'])->createView();
        $valuesForm = $withFields ? self::forms()->create(ContentValuesType::class, null, [
            'schema'          => $schema,
            'locale'          => 'en',
            'values'          => $values['en'],
            'reference_pages' => ['home', 'fields'],
            'action'          => '/admin_page_builder_content?pageKey=fields&locale=en',
            'method'          => 'POST',
        ])->createView() : null;
        $factory ??= self::factory();

        return $factory->context([
            'page'                  => $factory->content($page, $addForm, $valuesForm, true, true),
            'schema_table'          => $factory->schemaTable($schema, $removeForms),
            'builder_page'          => $page,
            'pageKey'               => 'fields',
            'schema'                => $schema,
            'fieldValues'           => $values,
            'locales'               => ['en', 'es'],
            'active_locale'         => 'en',
            'field_types'           => [],
            'can_edit_schema'       => true,
            'can_edit_values'       => true,
            'reference_pages'       => ['home', 'fields'],
            'schema_add_form'       => $addForm,
            'schema_remove_forms'   => $removeForms,
            'values_form'           => $valuesForm,
            'asset_upload_enabled'  => $media,
            'asset_library_enabled' => $media,
            'asset_upload_url'      => $media ? '/admin_page_builder_asset_upload' : '',
            'asset_library_url'     => $media ? '/admin_page_builder_assets_list' : '',
            'asset_upload_csrf'     => $media ? 'REDACTED-TEST-upload' : '',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function seo(?PageBuilderKitPageFactory $factory = null): array
    {
        $page        = self::page(1, 'home', PageStatus::Published, 'classic');
        $translation = new BuilderPageTranslation()->setLocale('en')->setTitle('Home')->setSlug('home');
        $form        = self::forms()->create(BuilderPageSeoType::class, $translation)->createView();
        $factory ??= self::factory();

        return $factory->context([
            'page'         => $factory->seo($page, $form),
            'builder_page' => $page,
            'page_key'     => 'home',
            'locales'      => ['en', 'es'],
            'locale'       => 'en',
            'form'         => $form,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function sections(bool $empty = false, PageStatus $status = PageStatus::Draft, ?PageBuilderKitPageFactory $factory = null): array
    {
        $page = self::page(2, 'legacy', $status, 'classic');
        $tree = $empty ? [] : [[
            'id'      => 's1',
            'label'   => 'Section 1',
            'columns' => [
                ['id' => 'c1', 'width' => 12, 'widgets' => [
                    ['id' => 'w1', 'type' => 'heading', 'depth' => 0, 'label' => 'widget.heading.label', 'props' => ['text' => 'Hello'], 'fields' => ['text'], 'children' => []],
                    ['id' => 'w2', 'type' => 'spacer', 'depth' => 1, 'label' => 'widget.spacer.label', 'props' => [], 'fields' => [], 'children' => []],
                ]],
                ['id' => 'c2', 'width' => 6, 'widgets' => []],
            ],
        ]];
        $form          = self::forms()->create(SectionsEditType::class, null, ['sections_tree' => $tree])->createView();
        $publishForm   = self::forms()->create(CsrfPostType::class, ['_redirect' => '/back'], ['action' => '/admin_page_builder_document_publish?pageKey=legacy', 'method' => 'POST'])->createView();
        $unpublishForm = self::forms()->create(CsrfPostType::class, ['_redirect' => '/back'], ['action' => '/admin_page_builder_document_unpublish?pageKey=legacy', 'method' => 'POST'])->createView();
        $factory ??= self::factory();

        return $factory->context([
            'page'           => $factory->sections($page, $form, $tree !== [], 'en', $publishForm, $unpublishForm),
            'builder_page'   => $page,
            'page_key'       => 'legacy',
            'locales'        => ['en', 'es'],
            'locale'         => 'en',
            'sections_tree'  => $tree,
            'is_classic'     => true,
            'form'           => $form,
            'publish_form'   => $publishForm,
            'unpublish_form' => $unpublishForm,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function revisions(bool $empty = false, ?PageBuilderKitPageFactory $factory = null): array
    {
        $page      = self::page(1, 'home', PageStatus::Published, 'grapesjs');
        $revisions = $empty ? [] : [self::revision(7, $page, 'Before redesign'), self::revision(6, $page, null)];
        $restore   = [];
        foreach ($revisions as $revision) {
            $restore[(string) $revision->getId()] = self::forms()->create(CsrfPostType::class, null, [
                'action' => '/admin_page_builder_revisions_restore?pageKey=home&revisionId=' . $revision->getId(),
                'method' => 'POST',
            ])->createView();
        }
        $factory ??= self::factory();

        return $factory->context([
            'page'          => $factory->revisions($page, $revisions, $restore, 30),
            'builder_page'  => $page,
            'page_key'      => 'home',
            'revisions'     => $revisions,
            'engine'        => 'grapesjs',
            'max_per_page'  => 30,
            'create_form'   => self::forms()->create(RevisionCreateType::class, null, ['action' => '/admin_page_builder_revisions_create?pageKey=home', 'method' => 'POST'])->createView(),
            'restore_forms' => $restore,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function revisionDiff(bool $identical = false, ?PageBuilderKitPageFactory $factory = null): array
    {
        $page  = self::page(1, 'home', PageStatus::Published, 'grapesjs');
        $left  = ['version' => 2, 'engine' => 'grapesjs', 'html' => "<h1>Hello</h1>\n<p>One</p>", 'css' => 'h1{color:red}', 'grapes' => [], 'localeContent' => []];
        $right = $identical ? $left : ['version' => 2, 'engine' => 'grapesjs', 'html' => "<h1>Hello</h1>\n<p>Two</p>", 'css' => '', 'grapes' => [], 'localeContent' => []];
        $factory ??= self::factory();

        return $factory->context([
            'page'         => $factory->revisionDiff($page, 7),
            'builder_page' => $page,
            'page_key'     => 'home',
            'revisionId'   => 7,
            'diff'         => ['revisionId' => 7, ...new DocumentDiff()->compare($left, $right, [], [])],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function templates(bool $empty = false, ?PageBuilderKitPageFactory $factory = null): array
    {
        $pages     = $empty ? [] : [self::page(1, 'home', PageStatus::Published, 'grapesjs')];
        $templates = $empty ? [] : [new BuilderPageTemplate()->setTemplateKey('landing')->setLabel('Landing page')];
        $saveForms = [];
        foreach ($pages as $page) {
            $saveForms[$page->getPageKey()] = self::forms()->create(TemplateSaveType::class, null, ['action' => '/admin_page_builder_templates_save?pageKey=' . $page->getPageKey(), 'method' => 'POST'])->createView();
        }
        $applyForms  = [];
        $deleteForms = [];
        foreach ($templates as $template) {
            $key               = $template->getTemplateKey();
            $applyForms[$key]  = self::forms()->create(TemplateApplyType::class, null, ['action' => '/admin_page_builder_templates_apply?templateKey=' . $key, 'method' => 'POST'])->createView();
            $deleteForms[$key] = self::forms()->create(CsrfPostType::class, null, ['action' => '/admin_page_builder_templates_delete?templateKey=' . $key, 'method' => 'POST'])->createView();
        }
        $factory ??= self::factory();

        return $factory->context([
            'page'         => $factory->templates($templates, $applyForms, $deleteForms),
            'templates'    => $templates,
            'pages'        => $pages,
            'save_forms'   => $saveForms,
            'import_form'  => self::forms()->create(TemplateImportType::class, null, ['action' => '/admin_page_builder_templates_import', 'method' => 'POST'])->createView(),
            'apply_forms'  => $applyForms,
            'delete_forms' => $deleteForms,
        ]);
    }

    public static function factory(?PageBuilderKitPages $pages = null, ?PageBuilderKitAccessCheckerInterface $access = null, bool $revisions = true): PageBuilderKitPageFactory
    {
        return new PageBuilderKitPageFactory(self::urlGenerator(), $access ?? new AllowAllPageBuilderKitAccessChecker(), $pages ?? new PageBuilderKitPages(), $revisions);
    }

    public static function page(int $id, string $key, PageStatus $status, string $engine): BuilderPage
    {
        $page = new BuilderPage()->setPageKey($key)->setStatus($status);
        new ReflectionProperty(BuilderPage::class, 'id')->setValue($page, $id);
        $document = new BuilderDocument()->setPage($page)->setStructure($engine === 'grapesjs'
            ? ['version' => 2, 'engine' => 'grapesjs', 'html' => '', 'css' => '']
            : ['version' => 1, 'sections' => []]);
        $page->setDocument($document);

        return $page;
    }

    public static function urlGenerator(): UrlGeneratorInterface
    {
        return new class implements UrlGeneratorInterface {
            private RequestContext $context;

            public function __construct()
            {
                $this->context = new RequestContext();
            }

            /**
             * @param array<string, mixed> $parameters
             */
            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                return '/' . $name . ($parameters === [] ? '' : '?' . http_build_query($parameters));
            }

            public function setContext(RequestContext $context): void
            {
                $this->context = $context;
            }

            public function getContext(): RequestContext
            {
                return $this->context;
            }
        };
    }

    private static function revision(int $id, BuilderPage $page, ?string $label): BuilderPageRevision
    {
        $revision = new BuilderPageRevision()->setPage($page)->setLabel($label)->setStructure(['version' => 2, 'engine' => 'grapesjs']);
        new ReflectionProperty(BuilderPageRevision::class, 'id')->setValue($revision, $id);
        new ReflectionProperty(BuilderPageRevision::class, 'createdAt')->setValue($revision, new DateTimeImmutable('2026-10-30 09:15:00'));

        return $revision;
    }

    private static function forms(): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->addExtension(new CsrfExtension(new class implements CsrfTokenManagerInterface {
                public function getToken(string $tokenId): CsrfToken
                {
                    return new CsrfToken($tokenId, 'REDACTED-TEST-' . $tokenId);
                }

                public function refreshToken(string $tokenId): CsrfToken
                {
                    return $this->getToken($tokenId);
                }

                public function removeToken(string $tokenId): ?string
                {
                    return null;
                }

                public function isTokenValid(CsrfToken $token): bool
                {
                    return true;
                }
            }))
            ->getFormFactory();
    }
}
