<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\DependencyInjection\Compiler;

use LogicException;
use Nowo\PageBuilderKitBundle\Media\PageBuilderAssetUploadStorageInterface;
use Nowo\PageBuilderKitBundle\Service\GrapesTwigPageContextProviderInterface;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function is_string;
use function sprintf;
use function str_contains;

/**
 * Since 2.0 (REQ-ARCH-001 rules 1 and 3, lesson W1-1): refuses, when the container is built, every trace of the two
 * host ports removed in 2.0 (deprecated since 1.15, see {@see self::REMOVED}):
 *
 * - a service definition whose loading errors name a removed interface. A class registered by resource autodiscovery
 *   that still implements it cannot be loaded, so Symfony keeps the definition with only an error
 *   (`Class "…\GrapesTwigContextProviderInterface" not found while loading "App\…"`) and never reads its tags or its
 *   `#[AsAlias]` / `#[AsDecorator]` attributes; unused, the definition would later be removed without a word. A Twig
 *   context provider dropped that way would render your pages without your data, and an asset storage of yours (or a
 *   decorator that checked uploads) would silently stop running. Only the exact class name, quoted as Symfony quotes
 *   it, matches: an unrelated missing class keeps its lazy error;
 * - a service or an alias registered under a removed id (`#[AsAlias]` of it on a loadable class, a `services.yaml`
 *   alias): 2.0 never reads those ids;
 * - a decorator of a removed id (also with `onInvalid: IGNORE`, which Symfony would drop without a word). In 1.x the
 *   built-in storage was aliased as `Nowo\PageBuilderKitBundle\Media\PageBuilderAssetStorageInterface`;
 * - a service tagged `nowo_page_builder_kit.grapes_twig_context` whose class does not implement
 *   {@see GrapesTwigPageContextProviderInterface} (or cannot be loaded), for example one tagged by hand in
 *   `services.yaml` that still implements the removed interface.
 *
 * Registered twice before optimization: at priority 1000, before any other pass looks at the definitions (loading
 * errors), and at priority 99, after Symfony has turned `#[AsDecorator]` attributes into decorated services and applied
 * the autoconfigured tags. Each message names the service, the removed interface, the version that deprecated it and
 * the replacement.
 *
 * @internal registered by the bundle
 */
final class PageBuilderRemovedPortsPass implements CompilerPassInterface
{
    /** The tag of the Twig context providers. */
    public const string CONTEXT_PROVIDER_TAG = 'nowo_page_builder_kit.grapes_twig_context';

    /**
     * Host ports removed in 2.0 (deprecated since 1.15) => their replacement.
     *
     * @var array<string, string>
     */
    public const array REMOVED = [
        'Nowo\\PageBuilderKitBundle\\Media\\PageBuilderAssetStorageInterface'     => PageBuilderAssetUploadStorageInterface::class,
        'Nowo\\PageBuilderKitBundle\\Service\\GrapesTwigContextProviderInterface' => GrapesTwigPageContextProviderInterface::class,
    ];

    /**
     * @throws LogicException on the first trace of a removed port
     */
    public function process(ContainerBuilder $container): void
    {
        foreach (self::REMOVED as $removed => $replacement) {
            if ($container->hasAlias($removed) || $container->hasDefinition($removed)) {
                $target = $container->hasAlias($removed) ? sprintf(' (an alias of "%s")', (string) $container->getAlias($removed)) : '';

                throw new LogicException(sprintf('The service id "%s"%s is no longer read by NowoPageBuilderKitBundle. %s', $removed, $target, self::hint($removed, $replacement)));
            }
        }

        foreach ($container->getDefinitions() as $id => $definition) {
            foreach (self::REMOVED as $removed => $replacement) {
                foreach ($definition->getErrors() as $error) {
                    if (self::names((string) $error, $removed)) {
                        throw new LogicException(sprintf('The service "%s" cannot be loaded: %s %s', $id, (string) $error, self::hint($removed, $replacement)));
                    }
                }

                if (($definition->getDecoratedService()[0] ?? null) === $removed) {
                    throw new LogicException(sprintf('The service "%s" decorates "%s", which no longer exists. %s', $id, $removed, self::hint($removed, $replacement)));
                }
            }
        }

        $this->checkContextProviders($container);
    }

    /**
     * Whether a loading error names exactly `$removed` (quoted as PHP and Symfony quote class names), so that
     * `…\FooInterface` never matches `…\FooPolicyInterface`.
     */
    public static function names(string $message, string $removed): bool
    {
        return str_contains($message, '"' . $removed . '"');
    }

    /**
     * What replaces a removed port, for the build errors.
     */
    public static function hint(string $removed, string $replacement): string
    {
        return sprintf('"%s" (deprecated since 1.15) was removed in 2.0, so a class that still implements it no longer loads: implement "%s" instead; see UPGRADING.md "To 1.15.0" and "To 2.0.0".', $removed, $replacement);
    }

    /**
     * A tagged Twig context provider must implement the 1.15 port: a provider the render service could not call would
     * otherwise render pages without the host's data.
     */
    private function checkContextProviders(ContainerBuilder $container): void
    {
        $removed = 'Nowo\\PageBuilderKitBundle\\Service\\GrapesTwigContextProviderInterface';
        foreach ($container->findTaggedServiceIds(self::CONTEXT_PROVIDER_TAG) as $id => $tags) {
            $definition = $container->getDefinition($id);
            if ($definition->getFactory() !== null || $definition->isSynthetic()) {
                continue; // the class is only known at run time; PageRenderProvider refuses a wrong one
            }

            $class = $container->getParameterBag()->resolveValue($definition->getClass() ?? $id);
            if (!is_string($class)) {
                continue;
            }

            $reflection = $container->getReflectionClass($class, false);
            if (!$reflection instanceof ReflectionClass) {
                throw new LogicException(sprintf('The Twig context provider "%s" (tag "%s") cannot be loaded: class "%s" not found or one of its interfaces is missing. %s', $id, self::CONTEXT_PROVIDER_TAG, $class, self::hint($removed, GrapesTwigPageContextProviderInterface::class)));
            }

            if (!$reflection->implementsInterface(GrapesTwigPageContextProviderInterface::class)) {
                throw new LogicException(sprintf('The Twig context provider "%s" (tag "%s", class "%s") does not implement "%s", the only Twig context provider port since 2.0. %s', $id, self::CONTEXT_PROVIDER_TAG, $class, GrapesTwigPageContextProviderInterface::class, self::hint($removed, GrapesTwigPageContextProviderInterface::class)));
            }
        }
    }
}
