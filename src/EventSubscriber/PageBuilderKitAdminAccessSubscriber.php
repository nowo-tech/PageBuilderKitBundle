<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\EventSubscriber;

use Nowo\PageBuilderKitBundle\Enum\PageBuilderCapability;
use Nowo\PageBuilderKitBundle\Security\PageBuilderKitAccessCheckerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

use function is_string;
use function sprintf;
use function str_starts_with;

final readonly class PageBuilderKitAdminAccessSubscriber implements EventSubscriberInterface
{
    /**
     * null = any authenticated editor (canAccess).
     * string = single capability.
     * list = any of the listed capabilities (OR).
     *
     * @var array<string, list<string>|string|null>
     */
    private const ROUTE_CAPABILITIES = [
        'admin_page_builder_list'               => null,
        'admin_page_builder_canvas'             => PageBuilderCapability::Layout->value,
        'admin_page_builder_sections'           => PageBuilderCapability::Layout->value,
        'admin_page_builder_asset_upload'       => PageBuilderCapability::Layout->value,
        'admin_page_builder_document_get'       => [PageBuilderCapability::Layout->value, PageBuilderCapability::Content->value],
        'admin_page_builder_document_save'      => PageBuilderCapability::Layout->value,
        'admin_page_builder_document_publish'   => PageBuilderCapability::Publish->value,
        'admin_page_builder_document_unpublish' => PageBuilderCapability::Publish->value,
        'admin_page_builder_document_duplicate' => PageBuilderCapability::Layout->value,
        'admin_page_builder_document_export'    => [
            PageBuilderCapability::Layout->value,
            PageBuilderCapability::Content->value,
            PageBuilderCapability::Templates->value,
        ],
        'admin_page_builder_document_import'      => PageBuilderCapability::Templates->value,
        'admin_page_builder_seo'                  => PageBuilderCapability::Content->value,
        'admin_page_builder_content'              => [PageBuilderCapability::Content->value, PageBuilderCapability::Layout->value],
        'admin_page_builder_content_schema'       => PageBuilderCapability::Layout->value,
        'admin_page_builder_field_save'           => PageBuilderCapability::Content->value,
        'admin_page_builder_templates'            => PageBuilderCapability::Templates->value,
        'admin_page_builder_templates_save'       => PageBuilderCapability::Templates->value,
        'admin_page_builder_templates_apply'      => PageBuilderCapability::Templates->value,
        'admin_page_builder_templates_delete'     => PageBuilderCapability::Templates->value,
        'admin_page_builder_templates_export_all' => PageBuilderCapability::Templates->value,
        'admin_page_builder_templates_export'     => PageBuilderCapability::Templates->value,
        'admin_page_builder_templates_import'     => PageBuilderCapability::Templates->value,
        'admin_page_builder_revisions'            => PageBuilderCapability::Layout->value,
        'admin_page_builder_revisions_create'     => PageBuilderCapability::Layout->value,
        'admin_page_builder_revisions_restore'    => PageBuilderCapability::Layout->value,
        'admin_page_builder_revisions_diff'       => PageBuilderCapability::Layout->value,
        'admin_page_builder_revisions_json'       => PageBuilderCapability::Layout->value,
    ];

    public function __construct(
        private PageBuilderKitAccessCheckerInterface $accessChecker,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['onKernelController', 0],
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $route = $event->getRequest()->attributes->get('_route');
        if (!is_string($route) || !str_starts_with($route, 'admin_page_builder_')) {
            return;
        }

        if (!$this->accessChecker->canAccess()) {
            throw new AccessDeniedException('Page builder admin requires an authorized user.');
        }

        $required = self::ROUTE_CAPABILITIES[$route] ?? null;
        if ($required === null) {
            return;
        }

        if (is_string($required)) {
            if (!$this->accessChecker->can($required)) {
                throw new AccessDeniedException(sprintf('Page builder capability "%s" required.', $required));
            }

            return;
        }

        foreach ($required as $capability) {
            if ($this->accessChecker->can($capability)) {
                return;
            }
        }

        throw new AccessDeniedException('Page builder capability required for this route.');
    }
}
