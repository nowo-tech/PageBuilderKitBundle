<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Ui;

use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageRevision;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageTemplate;
use Nowo\PageBuilderKitBundle\Enum\PageStatus;
use Nowo\PageBuilderKitBundle\Security\PageBuilderKitAccessCheckerInterface;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\BackLink;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\Confirm;
use Nowo\UiKitBundle\View\EmptyState;
use Nowo\UiKitBundle\View\FormPage;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\Pagination;
use Nowo\UiKitBundle\View\SettingsPage;
use Nowo\UiKitBundle\View\SettingsSection;
use Nowo\UiKitBundle\View\ShowPage;
use Nowo\UiKitBundle\View\Table;
use Symfony\Component\Form\FormView;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use function is_string;
use function strtoupper;

/**
 * Builds the UiKit view models of the Page Builder Kit admin pages (REQ-UI-003): page headers (title, intro, actions,
 * back link), columns, row actions (POST + CSRF FormViews behind a UiKit confirm `<dialog>` for duplicate, restore,
 * apply, delete and remove), pagination and empty states. Capabilities (`layout`, `content`, `publish`, `templates`)
 * decide which actions a page offers; the controllers still enforce them on every request. The controllers and the
 * page contract test share it, so the test renders exactly the view models the application gets. Stateless: safe in
 * worker mode.
 */
final readonly class PageBuilderKitPageFactory
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private PageBuilderKitAccessCheckerInterface $access,
        private PageBuilderKitPages $pages = new PageBuilderKitPages(),
        private bool $revisionsEnabled = false,
    ) {
    }

    /**
     * Template of a page: the `web_ui.templates.<page>` override, else the bundle template.
     */
    public function template(string $page): string
    {
        return $this->pages->template($page);
    }

    /**
     * Page variables plus the shared ones (legacy layout bridge).
     *
     * @param array<string, mixed> $vars
     *
     * @return array<string, mixed>
     */
    public function context(array $vars): array
    {
        return $this->pages->context($vars);
    }

    /**
     * Editor engine of a page document: `grapesjs` or `classic` (schema v1 sections / columns / widgets).
     */
    public static function engine(BuilderPage $page): string
    {
        $engine = $page->getDocument()?->getStructure()['engine'] ?? null;

        return is_string($engine) && $engine === 'grapesjs' ? 'grapesjs' : 'classic';
    }

    /**
     * Pages list: Templates in the header; per row Edit (canvas, or the classic sections editor), Content, SEO,
     * Export, Duplicate (POST form behind a dialog asking the new key) and Revisions, by capability.
     *
     * @param list<BuilderPage> $pages
     * @param array<string, FormView> $duplicateForms page key => duplicate form (field pageKey)
     */
    public function index(array $pages, Pagination $pagination, array $duplicateForms): ListPage
    {
        $headerActions = [];
        if ($this->access->can('templates')) {
            $headerActions[] = new Action(
                'custom',
                PageBuilderKitPages::label('admin.templates.title'),
                $this->url('admin_page_builder_templates'),
                variant: 'secondary',
                attributes: ['data-testid' => 'pbk-templates-link'],
            );
        }

        $rowActions = [];
        foreach ($pages as $page) {
            $rowActions[$page->getPageKey()] = $this->pageActions($page, $duplicateForms[$page->getPageKey()] ?? null);
        }

        return new ListPage(
            header: new PageHeader(
                PageBuilderKitPages::label('admin.pages.title'),
                PageBuilderKitPages::label('admin.pages.intro'),
                $headerActions,
            ),
            columns: [
                new Column('pageKey', PageBuilderKitPages::label('admin.pages.col_key'), required: true),
                new Column('status', PageBuilderKitPages::label('admin.pages.col_status')),
                new Column('engine', PageBuilderKitPages::label('admin.pages.col_engine'), priority: 2),
            ],
            rows: $pages,
            rowActions: static fn (BuilderPage $row): array => $rowActions[$row->getPageKey()] ?? [],
            pagination: $pagination,
            empty: new EmptyState(null, PageBuilderKitPages::label('admin.pages.empty')),
            bundle: PageBuilderKitPages::BUNDLE,
            layout: $this->pages->layout(),
            attributes: ['data-testid' => 'pbk-pages'],
            actionsLabel: PageBuilderKitPages::label('admin.pages.col_actions'),
        );
    }

    /**
     * GrapesJS canvas (unique page): header with SEO, Revisions and Preview; the save / publish / unpublish buttons
     * are script buttons of the canvas toolbar.
     */
    public function canvas(BuilderPage $page): ShowPage
    {
        $key     = $page->getPageKey();
        $actions = [$this->seoAction($key)];
        if ($this->revisionsEnabled) {
            $actions[] = $this->revisionsAction($key);
        }
        $actions[] = $this->previewAction($key);

        return new ShowPage(
            header: new PageHeader(
                PageBuilderKitPages::label('admin.canvas.title', ['%key%' => $key]),
                PageBuilderKitPages::label('admin.canvas.grapes_hint'),
                $actions,
                back: $this->backToList(),
            ),
            bundle: PageBuilderKitPages::BUNDLE,
            layout: $this->pages->layout(),
            attributes: ['data-testid' => 'pbk-canvas-page'],
        );
    }

    /**
     * Content fields (settings scaffold): section `schema` (field list with Remove behind a confirm, add form; layout
     * capability) and section `values` (per-locale values form; content capability).
     */
    public function content(
        BuilderPage $page,
        ?FormView $schemaAddForm,
        ?FormView $valuesForm,
        bool $canSchema,
        bool $canValues,
    ): SettingsPage {
        $key      = $page->getPageKey();
        $sections = [];
        if ($canSchema) {
            $sections[] = new SettingsSection(
                'schema',
                PageBuilderKitPages::label('admin.content.schema'),
                form: $schemaAddForm,
                submitLabel: PageBuilderKitPages::label('admin.content.add_field'),
            );
        }
        if ($canValues) {
            $sections[] = new SettingsSection(
                'values',
                PageBuilderKitPages::label('admin.content.values'),
                form: $valuesForm,
                submitLabel: PageBuilderKitPages::label('admin.content.save_values'),
            );
        }

        $actions = [];
        if ($this->access->canLayout()) {
            $actions[] = $this->editAction($page);
        }

        return new SettingsPage(
            header: new PageHeader(
                PageBuilderKitPages::label('admin.content.title', ['%key%' => $key]),
                PageBuilderKitPages::label('admin.content.hint'),
                $actions,
                back: $this->backToList(),
            ),
            sections: $sections,
            bundle: PageBuilderKitPages::BUNDLE,
            layout: $this->pages->layout(),
            attributes: ['data-testid' => 'pbk-content'],
        );
    }

    /**
     * Schema fields of the content page: key, type, label, required; row action Remove (POST form behind a confirm).
     *
     * @param list<array<string, mixed>> $schema
     * @param array<string, FormView> $removeForms field key => remove form
     */
    public function schemaTable(array $schema, array $removeForms): Table
    {
        return new Table(
            columns: [
                new Column('key', PageBuilderKitPages::label('admin.content.col_key'), required: true),
                new Column('type', PageBuilderKitPages::label('admin.content.col_type')),
                new Column('display_label', PageBuilderKitPages::label('admin.content.col_label'), priority: 2),
            ],
            rows: $schema,
            rowActions: function (array $field) use ($removeForms): array {
                $key  = is_string($field['key'] ?? null) ? $field['key'] : '';
                $form = $removeForms[$key] ?? null;
                if (!$form instanceof FormView) {
                    return [];
                }

                return [Action::delete(
                    $this->formAction($form),
                    confirm: new Confirm(
                        PageBuilderKitPages::label('admin.content.remove_title'),
                        PageBuilderKitPages::label('admin.content.remove_confirm', ['%key%' => $key]),
                        PageBuilderKitPages::label('admin.content.remove_field'),
                        cancelLabel: PageBuilderKitPages::label('admin.confirm.cancel'),
                    ),
                    label: PageBuilderKitPages::label('admin.content.remove_field'),
                    form: $form,
                )->withConfirmScope($key)];
            },
            actionsLabel: PageBuilderKitPages::label('admin.pages.col_actions'),
            actionsLabelVisible: false,
            responsive: 'scroll',
            bundle: PageBuilderKitPages::BUNDLE,
            label: PageBuilderKitPages::label('admin.content.schema'),
        );
    }

    /**
     * SEO of one locale (form scaffold): header with Revisions and Edit, back link to the list.
     */
    public function seo(BuilderPage $page, FormView $form): FormPage
    {
        $key     = $page->getPageKey();
        $actions = [];
        if ($this->revisionsEnabled) {
            $actions[] = $this->revisionsAction($key);
        }
        $actions[] = $this->editAction($page);

        return new FormPage(
            header: new PageHeader(
                PageBuilderKitPages::label('admin.seo.title', ['%key%' => $key]),
                PageBuilderKitPages::label('admin.seo.hint'),
                $actions,
                back: $this->backToList(),
            ),
            form: $form,
            submitLabel: PageBuilderKitPages::label('admin.seo.save'),
            cancelHref: $this->url('admin_page_builder_list'),
            cancelLabel: PageBuilderKitPages::label('admin.confirm.cancel'),
            bundle: PageBuilderKitPages::BUNDLE,
            layout: $this->pages->layout(),
            attributes: ['data-testid' => 'pbk-seo'],
        );
    }

    /**
     * Classic (schema v1) sections editor (form scaffold): header with SEO, Revisions, Preview and Publish / Unpublish
     * (POST forms). Without sections the form is not rendered (body-only mode).
     */
    public function sections(BuilderPage $page, FormView $form, bool $hasSections, string $locale, FormView $publishForm, FormView $unpublishForm): FormPage
    {
        $key     = $page->getPageKey();
        $actions = [$this->seoAction($key)];
        if ($this->revisionsEnabled) {
            $actions[] = $this->revisionsAction($key);
        }
        $actions[] = $this->previewAction($key);
        if ($this->access->canPublish()) {
            $published = $page->getStatus() === PageStatus::Published;
            $actions[] = new Action(
                'custom',
                PageBuilderKitPages::label($published ? 'admin.canvas.unpublish' : 'admin.canvas.publish'),
                $this->formAction($published ? $unpublishForm : $publishForm),
                method: 'POST',
                variant: $published ? 'secondary' : 'primary',
                attributes: ['data-testid' => $published ? 'pbk-unpublish' : 'pbk-publish'],
                form: $published ? $unpublishForm : $publishForm,
            );
        }

        return new FormPage(
            header: new PageHeader(
                PageBuilderKitPages::label('admin.sections.title', ['%key%' => $key]),
                PageBuilderKitPages::label('admin.sections.hint'),
                $actions,
                back: $this->backToList(),
            ),
            form: $form,
            submitLabel: PageBuilderKitPages::label('admin.sections.save', ['%locale%' => strtoupper($locale)]),
            bundle: PageBuilderKitPages::BUNDLE,
            layout: $this->pages->layout(),
            attributes: ['data-testid' => 'pbk-sections'],
            formTag: $hasSections,
        );
    }

    /**
     * Revision history (list scaffold): Edit in the header; per row Diff and Restore (POST form behind a confirm).
     *
     * @param list<BuilderPageRevision> $revisions
     * @param array<int|string, FormView> $restoreForms revision id => restore form
     */
    public function revisions(BuilderPage $page, array $revisions, array $restoreForms, int $maxPerPage): ListPage
    {
        $key = $page->getPageKey();

        return new ListPage(
            header: new PageHeader(
                PageBuilderKitPages::label('admin.revisions.title', ['%key%' => $key]),
                PageBuilderKitPages::label('admin.revisions.hint', ['%max%' => $maxPerPage]),
                [$this->editAction($page)],
                back: $this->backToList(),
            ),
            columns: [
                new Column('id', PageBuilderKitPages::label('admin.revisions.col_id'), required: true),
                new Column('label', PageBuilderKitPages::label('admin.revisions.col_label')),
                new Column('createdAt', PageBuilderKitPages::label('admin.revisions.col_when')),
                new Column('engine', PageBuilderKitPages::label('admin.revisions.col_engine'), priority: 2),
            ],
            rows: $revisions,
            rowActions: function (BuilderPageRevision $revision) use ($key, $restoreForms): array {
                $id      = (int) $revision->getId();
                $actions = [new Action(
                    'view',
                    PageBuilderKitPages::label('admin.revisions.diff'),
                    $this->url('admin_page_builder_revisions_diff', ['pageKey' => $key, 'revisionId' => $id]),
                    icon: 'view',
                )];
                $form = $restoreForms[$id] ?? null;
                if ($form instanceof FormView) {
                    $actions[] = new Action(
                        'restore',
                        PageBuilderKitPages::label('admin.revisions.restore'),
                        $this->formAction($form),
                        method: 'POST',
                        confirm: new Confirm(
                            PageBuilderKitPages::label('admin.revisions.restore_title', ['%id%' => $id]),
                            PageBuilderKitPages::label('admin.revisions.restore_confirm'),
                            PageBuilderKitPages::label('admin.revisions.restore'),
                            'primary',
                            PageBuilderKitPages::label('admin.confirm.cancel'),
                        ),
                        icon: 'restore',
                        form: $form,
                    );
                }

                return $actions;
            },
            empty: new EmptyState(null, PageBuilderKitPages::label('admin.revisions.empty')),
            bundle: PageBuilderKitPages::BUNDLE,
            layout: $this->pages->layout(),
            attributes: ['data-testid' => 'pbk-revisions'],
            actionsLabel: PageBuilderKitPages::label('admin.pages.col_actions'),
        );
    }

    /**
     * Revision diff (show scaffold): header with a back link to the history.
     */
    public function revisionDiff(BuilderPage $page, int $revisionId): ShowPage
    {
        $key = $page->getPageKey();

        return new ShowPage(
            header: new PageHeader(
                PageBuilderKitPages::label('admin.revisions.diff_title', ['%key%' => $key, '%id%' => $revisionId]),
                PageBuilderKitPages::label('admin.revisions.diff_hint'),
                back: new BackLink(
                    $this->url('admin_page_builder_revisions', ['pageKey' => $key]),
                    PageBuilderKitPages::label('admin.pages.revisions'),
                    'pbk-back',
                ),
            ),
            bundle: PageBuilderKitPages::BUNDLE,
            layout: $this->pages->layout(),
            attributes: ['data-testid' => 'pbk-revision-diff'],
        );
    }

    /**
     * Templates (list scaffold): Export all in the header; per row Export, Apply (POST form behind a dialog with the
     * target page fields) and Delete (POST form behind a confirm).
     *
     * @param list<BuilderPageTemplate> $templates
     * @param array<string, FormView> $applyForms template key => apply form
     * @param array<string, FormView> $deleteForms template key => delete form
     */
    public function templates(array $templates, array $applyForms, array $deleteForms): ListPage
    {
        return new ListPage(
            header: new PageHeader(
                PageBuilderKitPages::label('admin.templates.title'),
                PageBuilderKitPages::label('admin.templates.hint'),
                [new Action(
                    'export',
                    PageBuilderKitPages::label('admin.templates.export_all'),
                    $this->url('admin_page_builder_templates_export_all'),
                    icon: 'export',
                    variant: 'secondary',
                )],
                back: $this->backToList(),
            ),
            columns: [
                new Column('templateKey', PageBuilderKitPages::label('admin.templates.key'), required: true),
                new Column('label', PageBuilderKitPages::label('admin.templates.label')),
            ],
            rows: $templates,
            rowActions: function (BuilderPageTemplate $template) use ($applyForms, $deleteForms): array {
                $key     = $template->getTemplateKey();
                $actions = [new Action(
                    'export',
                    PageBuilderKitPages::label('admin.templates.export'),
                    $this->url('admin_page_builder_templates_export', ['templateKey' => $key]),
                    icon: 'export',
                )];
                if (isset($applyForms[$key])) {
                    $actions[] = new Action(
                        'custom',
                        PageBuilderKitPages::label('admin.templates.apply'),
                        $this->formAction($applyForms[$key]),
                        method: 'POST',
                        confirm: new Confirm(
                            PageBuilderKitPages::label('admin.templates.apply_title', ['%key%' => $key]),
                            PageBuilderKitPages::label('admin.templates.apply_hint'),
                            PageBuilderKitPages::label('admin.templates.apply'),
                            'primary',
                            PageBuilderKitPages::label('admin.confirm.cancel'),
                        ),
                        icon: 'copy',
                        form: $applyForms[$key],
                    );
                }
                if (isset($deleteForms[$key])) {
                    $actions[] = Action::delete(
                        $this->formAction($deleteForms[$key]),
                        confirm: new Confirm(
                            PageBuilderKitPages::label('admin.templates.delete_title', ['%key%' => $key]),
                            PageBuilderKitPages::label('admin.templates.delete_confirm'),
                            PageBuilderKitPages::label('admin.templates.delete'),
                            cancelLabel: PageBuilderKitPages::label('admin.confirm.cancel'),
                        ),
                        label: PageBuilderKitPages::label('admin.templates.delete'),
                        form: $deleteForms[$key],
                    );
                }

                return $actions;
            },
            empty: new EmptyState(null, PageBuilderKitPages::label('admin.templates.empty')),
            bundle: PageBuilderKitPages::BUNDLE,
            layout: $this->pages->layout(),
            attributes: ['data-testid' => 'pbk-templates'],
            actionsLabel: PageBuilderKitPages::label('admin.pages.col_actions'),
        );
    }

    /**
     * Row actions of one page in the list, by capability.
     *
     * @return list<Action>
     */
    private function pageActions(BuilderPage $page, ?FormView $duplicateForm): array
    {
        $key       = $page->getPageKey();
        $canLayout = $this->access->canLayout();
        $canText   = $this->access->canContent();
        $actions   = [];

        if ($canLayout) {
            $actions[] = $this->editAction($page);
        }
        if ($canText || $canLayout) {
            $actions[] = new Action(
                'custom',
                PageBuilderKitPages::label('admin.pages.content'),
                $this->url('admin_page_builder_content', ['pageKey' => $key]),
                icon: 'book',
                variant: $canLayout ? null : 'primary',
                attributes: ['data-testid' => 'pbk-content-link'],
            );
        }
        if ($canText) {
            $actions[] = $this->seoAction($key, true);
        }
        if ($canLayout || $canText || $this->access->can('templates')) {
            $actions[] = new Action(
                'export',
                PageBuilderKitPages::label('admin.pages.export'),
                $this->url('admin_page_builder_document_export', ['pageKey' => $key]),
                icon: 'export',
            );
        }
        if ($canLayout && $duplicateForm instanceof FormView) {
            $actions[] = new Action(
                'copy',
                PageBuilderKitPages::label('admin.pages.duplicate'),
                $this->formAction($duplicateForm),
                method: 'POST',
                confirm: new Confirm(
                    PageBuilderKitPages::label('admin.pages.duplicate_title', ['%key%' => $key]),
                    PageBuilderKitPages::label('admin.pages.duplicate_hint'),
                    PageBuilderKitPages::label('admin.pages.duplicate'),
                    'primary',
                    PageBuilderKitPages::label('admin.confirm.cancel'),
                ),
                icon: 'copy',
                form: $duplicateForm,
            );
        }
        if ($canLayout && $this->revisionsEnabled) {
            $actions[] = $this->revisionsAction($key, true);
        }

        return $actions;
    }

    /**
     * Edit link of a page: the GrapesJS canvas, or the classic sections editor for schema v1 documents.
     */
    private function editAction(BuilderPage $page): Action
    {
        $key = $page->getPageKey();
        if (self::engine($page) === 'grapesjs') {
            return new Action(
                'edit',
                PageBuilderKitPages::label('admin.pages.edit'),
                $this->url('admin_page_builder_canvas', ['pageKey' => $key]),
                icon: 'edit',
                variant: 'primary',
                attributes: ['data-testid' => 'pbk-edit'],
            );
        }

        return new Action(
            'edit',
            PageBuilderKitPages::label('admin.pages.sections'),
            $this->url('admin_page_builder_sections', ['pageKey' => $key]),
            icon: 'edit',
            variant: 'primary',
            attributes: ['data-testid' => 'pbk-edit'],
        );
    }

    private function seoAction(string $key, bool $row = false): Action
    {
        return new Action(
            'config',
            PageBuilderKitPages::label('admin.pages.seo'),
            $this->url('admin_page_builder_seo', ['pageKey' => $key]),
            icon: 'config',
            variant: $row ? null : 'secondary',
            attributes: ['data-testid' => 'pbk-seo-link'],
        );
    }

    private function revisionsAction(string $key, bool $row = false): Action
    {
        return new Action(
            'custom',
            PageBuilderKitPages::label('admin.pages.revisions'),
            $this->url('admin_page_builder_revisions', ['pageKey' => $key]),
            icon: 'restore',
            variant: $row ? null : 'secondary',
            attributes: ['data-testid' => 'pbk-revisions-link'],
        );
    }

    private function previewAction(string $key): Action
    {
        return new Action(
            'view',
            PageBuilderKitPages::label('admin.canvas.preview'),
            $this->url('page_builder_public_render', ['pageKey' => $key]),
            icon: 'view',
            variant: 'secondary',
            attributes: ['target' => '_blank', 'rel' => 'noopener'],
        );
    }

    private function backToList(): BackLink
    {
        return new BackLink($this->url('admin_page_builder_list'), PageBuilderKitPages::label('admin.pages.list'), 'pbk-back');
    }

    /**
     * Target URL of a POST FormView (its `action` option).
     */
    private function formAction(FormView $form): string
    {
        $action = $form->vars['action'] ?? null;

        return is_string($action) && $action !== '' ? $action : '#';
    }

    /**
     * @param array<string, int|string> $params
     */
    private function url(string $route, array $params = []): string
    {
        return $this->urlGenerator->generate($route, $params);
    }
}
