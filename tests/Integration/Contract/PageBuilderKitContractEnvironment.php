<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Integration\Contract;

use Nowo\PageBuilderKitBundle\Security\Html\SafeUrl;
use Twig\Environment;
use Twig\TwigFilter;

use function dirname;

/**
 * Twig environment of the UiKit page contract test for the bundle: bundle views and catalogues, the bundle URL filter,
 * strict missing-translation rule.
 *
 * @phpstan-require-extends \Nowo\UiKitBundle\Test\PageContractTestCase
 */
trait PageBuilderKitContractEnvironment
{
    protected static function templatePaths(): array
    {
        return ['NowoPageBuilderKitBundle' => dirname(__DIR__, 3) . '/src/Resources/views'];
    }

    protected static function translationDirs(): array
    {
        return [dirname(__DIR__, 3) . '/src/Resources/translations'];
    }

    protected static function templatesDocument(): string
    {
        return dirname(__DIR__, 3) . '/docs/TEMPLATES.md';
    }

    protected static function strictMissingTranslations(): bool
    {
        return true;
    }

    protected function configureTwig(Environment $twig): void
    {
        $twig->addFilter(new TwigFilter('pbk_safe_url', SafeUrl::filter(...)));
    }
}
