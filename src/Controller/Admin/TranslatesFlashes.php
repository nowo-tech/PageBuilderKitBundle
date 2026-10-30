<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Controller\Admin;

use Nowo\PageBuilderKitBundle\Ui\PageBuilderKitPages;
use Symfony\Contracts\Translation\TranslatorInterface;

use function is_string;

/**
 * Flash messages of the admin controllers are translation keys of the bundle domain: they are translated when they
 * are added, so the UiKit `flashes` partial of any host layout prints them as is (REQ-UI-003). Text that is not a
 * catalogue key (exception messages) passes through unchanged. Uses the optional `translator` service locator entry;
 * no state.
 *
 * @phpstan-require-extends \Symfony\Bundle\FrameworkBundle\Controller\AbstractController
 */
trait TranslatesFlashes
{
    public static function getSubscribedServices(): array
    {
        return parent::getSubscribedServices() + ['translator' => '?' . TranslatorInterface::class];
    }

    protected function addFlash(string $type, mixed $message): void
    {
        if (is_string($message) && $this->container->has('translator')) {
            $translator = $this->container->get('translator');
            if ($translator instanceof TranslatorInterface) {
                $message = $translator->trans($message, [], PageBuilderKitPages::DOMAIN);
            }
        }

        parent::addFlash($type, $message);
    }
}
