<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Ui;

use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Enum\PageBuilderCapability;
use Nowo\PageBuilderKitBundle\Enum\PageStatus;
use Nowo\PageBuilderKitBundle\Security\PageBuilderKitAccessCheckerInterface;
use Nowo\PageBuilderKitBundle\Tests\Support\PageFixtures;
use Nowo\PageBuilderKitBundle\Ui\PageBuilderKitPageFactory;
use Nowo\PageBuilderKitBundle\Ui\PageBuilderKitPages;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Label;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\Pagination;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormView;

use function array_map;
use function in_array;

#[CoversClass(PageBuilderKitPageFactory::class)]
final class PageBuilderKitPageFactoryTest extends TestCase
{
    #[Test]
    public function engineReadsTheDocumentStructure(): void
    {
        self::assertSame('grapesjs', PageBuilderKitPageFactory::engine(PageFixtures::page(1, 'a', PageStatus::Draft, 'grapesjs')));
        self::assertSame('classic', PageBuilderKitPageFactory::engine(PageFixtures::page(1, 'a', PageStatus::Draft, 'classic')));
        self::assertSame('classic', PageBuilderKitPageFactory::engine(new BuilderPage()->setPageKey('none')));
    }

    #[Test]
    public function templateAndContextDelegateToThePages(): void
    {
        $factory = PageFixtures::factory(new PageBuilderKitPages(null, ['index' => 'host.html.twig']));

        self::assertSame('host.html.twig', $factory->template('index'));
        self::assertSame(['x' => 1, 'nowo_page_builder_legacy_layout' => null, 'nowo_page_builder_legacy_blocks' => []], $factory->context(['x' => 1]));
    }

    #[Test]
    public function theListOffersEveryActionWithFullAccess(): void
    {
        $page = PageFixtures::index()['page'];
        self::assertInstanceOf(ListPage::class, $page);

        self::assertSame(['pbk-templates-link'], array_map(static fn (Action $a): mixed => $a->attributes['data-testid'] ?? null, $page->header->actions));
        $rows = $page->rows;
        self::assertSame(['admin.pages.edit', 'admin.pages.content', 'admin.pages.seo', 'admin.pages.export', 'admin.pages.duplicate', 'admin.pages.revisions'], $this->labels($page->rowActions($rows[0], 0)));
        self::assertSame('admin.pages.sections', $this->labels($page->rowActions($rows[1], 1))[0]);
        self::assertSame('/admin_page_builder_document_duplicate?pageKey=home', $page->rowActions($rows[0], 0)[4]->href);
    }

    #[Test]
    public function aContentEditorOnlyGetsContentSeoAndExport(): void
    {
        $factory = PageFixtures::factory(null, $this->access(['content']), false);
        $home    = PageFixtures::page(1, 'home', PageStatus::Draft, 'grapesjs');
        $page    = $factory->index([$home], Pagination::fromTotal(1, 1, 50, [50], 50), ['home' => new FormView()]);

        self::assertSame([], $page->header->actions);
        $actions = $page->rowActions($home, 0);
        self::assertSame(['admin.pages.content', 'admin.pages.seo', 'admin.pages.export'], $this->labels($actions));
        self::assertSame('primary', $actions[0]->variant);
        self::assertSame([], $factory->index([PageFixtures::page(2, 'other', PageStatus::Draft, 'grapesjs')], Pagination::fromTotal(1, 1, 50, [50], 50), [])->rowActions(new BuilderPage(), 0));
    }

    #[Test]
    public function aTemplatesEditorOnlyGetsExport(): void
    {
        $factory = PageFixtures::factory(null, $this->access(['templates']), false);
        $home    = PageFixtures::page(1, 'home', PageStatus::Draft, 'grapesjs');

        self::assertSame(['admin.pages.export'], $this->labels($factory->index([$home], Pagination::fromTotal(1, 1, 50, [50], 50), [])->rowActions($home, 0)));
    }

    #[Test]
    public function headersFollowCapabilitiesAndRevisions(): void
    {
        $page    = PageFixtures::page(1, 'home', PageStatus::Published, 'grapesjs');
        $limited = PageFixtures::factory(null, $this->access(['content']), false);

        self::assertSame(['admin.pages.seo', 'admin.canvas.preview'], $this->labels($limited->canvas($page)->header->actions));
        self::assertSame(['admin.pages.seo', 'admin.pages.revisions', 'admin.canvas.preview'], $this->labels(PageFixtures::factory()->canvas($page)->header->actions));
        self::assertSame([], $limited->content($page, null, null, false, true)->header->actions);
        self::assertSame(['admin.pages.edit'], $this->labels($limited->seo($page, new FormView())->header->actions));
        self::assertSame(['admin.pages.revisions', 'admin.pages.edit'], $this->labels(PageFixtures::factory()->seo($page, new FormView())->header->actions));
        self::assertSame(['admin.pages.seo', 'admin.canvas.preview'], $this->labels($limited->sections($page, new FormView(), false, 'en', new FormView(), new FormView())->header->actions));
    }

    #[Test]
    public function sectionsOfferPublishOrUnpublish(): void
    {
        $draft     = PageFixtures::sections()['page'];
        $published = PageFixtures::sections(true, PageStatus::Published)['page'];

        self::assertSame('admin.canvas.publish', $this->labels($draft->header->actions)[3]);
        self::assertSame('/admin_page_builder_document_publish?pageKey=legacy', $draft->header->actions[3]->href);
        self::assertTrue($draft->formTag);
        self::assertSame('admin.canvas.unpublish', $this->labels($published->header->actions)[3]);
        self::assertFalse($published->formTag);
    }

    #[Test]
    public function rowActionsWithoutFormsAreLinksOnly(): void
    {
        $factory  = PageFixtures::factory();
        $page     = PageFixtures::page(1, 'home', PageStatus::Draft, 'grapesjs');
        $revision = PageFixtures::revisions()['revisions'][0];
        $template = PageFixtures::templates()['templates'][0];

        self::assertSame(['admin.revisions.diff'], $this->labels($factory->revisions($page, [$revision], [], 30)->rowActions($revision, 0)));
        self::assertSame(['admin.templates.export'], $this->labels($factory->templates([$template], [], [])->rowActions($template, 0)));
        self::assertSame([], $factory->schemaTable([['key' => 'a', 'type' => 'string']], [])->rowActions(['key' => 'a'], 0));
        self::assertSame([], $factory->schemaTable([['type' => 'string']], [])->rowActions(['type' => 'string'], 0));
        self::assertSame('#', $factory->schemaTable([['key' => 'a']], ['a' => new FormView()])->rowActions(['key' => 'a'], 0)[0]->href);
    }

    /**
     * @param list<Action> $actions
     *
     * @return list<string>
     */
    private function labels(array $actions): array
    {
        return array_map(static fn (Action $action): string => $action->label instanceof Label ? $action->label->key : (string) $action->label, $actions);
    }

    /**
     * @param list<string> $capabilities
     */
    private function access(array $capabilities): PageBuilderKitAccessCheckerInterface
    {
        return new readonly class($capabilities) implements PageBuilderKitAccessCheckerInterface {
            /**
             * @param list<string> $capabilities
             */
            public function __construct(private array $capabilities)
            {
            }

            public function canAccess(): bool
            {
                return true;
            }

            public function canLayout(): bool
            {
                return $this->can('layout');
            }

            public function canContent(): bool
            {
                return $this->can('content');
            }

            public function canPublish(): bool
            {
                return $this->can('publish');
            }

            public function canTemplates(): bool
            {
                return $this->can('templates');
            }

            public function can(PageBuilderCapability|string $capability): bool
            {
                return in_array($capability instanceof PageBuilderCapability ? $capability->value : $capability, $this->capabilities, true);
            }
        };
    }
}
