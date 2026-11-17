<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Resources;

use Nowo\PageBuilderKitBundle\DependencyInjection\Configuration;
use Nowo\PageBuilderKitBundle\Service\GrapesJsFrontendConfig;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Config\Definition\Processor;

use function dirname;

use const JSON_THROW_ON_ERROR;

/**
 * The editor JavaScript is imported from the CDN at the `cdn_version` default, while `grapes.min.css` is copied from the
 * `grapesjs` devDependency. Both must be the same GrapesJS version, or the editor runs with a mismatched stylesheet.
 */
#[CoversNothing]
final class GrapesJsVersionPinTest extends TestCase
{
    public function testThePackageJsonPinEqualsTheDefaultCdnVersion(): void
    {
        $root    = dirname(__DIR__, 3);
        $package = json_decode((string) file_get_contents($root . '/package.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($package);
        $pinned = $package['devDependencies']['grapesjs'] ?? null;

        $config  = new Processor()->processConfiguration(new Configuration(), [[]]);
        $default = $config['grapesjs']['cdn_version'];

        self::assertSame($default, $pinned, 'package.json devDependencies.grapesjs must be the exact default grapesjs.cdn_version (the vendored CSS comes from it).');
    }

    public function testTheLockfileResolvesTheSameVersion(): void
    {
        $lock    = (string) file_get_contents(dirname(__DIR__, 3) . '/pnpm-lock.yaml');
        $config  = new Processor()->processConfiguration(new Configuration(), [[]]);
        $version = $config['grapesjs']['cdn_version'];

        self::assertSame(1, preg_match('/^\s+grapesjs:\n\s+specifier: (\S+)\n\s+version: (\S+)$/m', $lock, $m));
        self::assertSame($version, $m[1]);
        self::assertSame($version, $m[2]);
    }

    public function testTheFrontendConfigAndTheCanvasSourceUseTheSameDefault(): void
    {
        $config  = new Processor()->processConfiguration(new Configuration(), [[]]);
        $default = $config['grapesjs']['cdn_version'];

        $parameter = new ReflectionClass(GrapesJsFrontendConfig::class)->getConstructor()?->getParameters()[1] ?? null;
        self::assertNotNull($parameter);
        self::assertSame('cdnVersion', $parameter->getName());
        self::assertSame($default, $parameter->getDefaultValue());

        $canvas = (string) file_get_contents(dirname(__DIR__, 3) . '/src/Resources/assets/src/page-builder-canvas.ts');
        self::assertStringContainsString("config.cdnVersion || '" . $default . "'", $canvas);
    }
}
