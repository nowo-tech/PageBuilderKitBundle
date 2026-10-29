<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\DependencyInjection\Compiler;

use LogicException;
use Nowo\PageBuilderKitBundle\Controller\Admin\PageListController;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Fails closed when the admin controllers are registered without form CSRF protection (REQ-SEC-005).
 *
 * JSON and manual admin endpoints check `CsrfTokenManagerInterface::isTokenValid()` themselves, but the create-page,
 * SEO and classic sections forms rely on the framework form CSRF extension. A host that disables
 * `framework.form.csrf_protection` (or has no CSRF token manager) would silently accept forged POSTs, so the
 * container refuses to compile instead.
 */
final class CsrfProtectionPass implements CompilerPassInterface
{
    /**
     * Throws when the admin controllers are registered and form CSRF protection or the CSRF token manager is unavailable.
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(PageListController::class)) {
            return;
        }

        $formCsrfDisabled = $container->hasParameter('form.type_extension.csrf.enabled')
            && $container->getParameter('form.type_extension.csrf.enabled') === false;

        if (
            !$container->has('security.csrf.token_manager')
            || !$container->hasDefinition('form.type_extension.csrf')
            || $formCsrfDisabled
        ) {
            throw new LogicException('NowoPageBuilderKitBundle: the admin UI requires CSRF protection. Enable framework.csrf_protection and framework.form.csrf_protection (composer require symfony/security-csrf).');
        }
    }
}
