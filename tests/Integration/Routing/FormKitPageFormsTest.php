<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Integration\Routing;

use Nowo\PageBuilderKitBundle\Form\BuilderPageCreateType;
use Nowo\PageBuilderKitBundle\Form\ContentValuesType;
use Nowo\PageBuilderKitBundle\Form\SectionsEditType;
use Nowo\PageBuilderKitBundle\Tests\Support\FormKitTestSupport;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Environment;

use function dirname;

/**
 * REQ-TWIG-005 rule 11 in a host-like kernel: FormKit injects its merger into the page form types and the
 * `page_builder_kit` profile prepended by the bundle themes them; every form renders translated labels (no raw keys).
 */
final class FormKitPageFormsTest extends TestCase
{
    private ?KernelInterface $kernel = null;

    public static function setUpBeforeClass(): void
    {
        new Filesystem()->remove(dirname(new RoutingTestKernel(false)->getCacheDir()));
    }

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    public function testEveryPageFormRendersWithTheProfileAndNoRawKeys(): void
    {
        $this->kernel = new RoutingTestKernel(false);
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $factory = $container->get(FormFactoryInterface::class);
        self::assertInstanceOf(FormFactoryInterface::class, $factory);
        $twig = $container->get('twig');
        self::assertInstanceOf(Environment::class, $twig);
        $template = $twig->createTemplate('{{ form(form) }}');

        $options = [
            ContentValuesType::class => [
                'schema' => [['key' => 'title', 'type' => 'string'], ['key' => 'ref', 'type' => 'reference']],
                'values' => [],
            ],
            SectionsEditType::class => [
                'sections_tree' => [['id' => 's1', 'columns' => [['widgets' => [['id' => 'w1', 'fields' => ['title'], 'props' => ['title' => 'Hi']]]]]]],
            ],
        ];

        foreach (FormKitTestSupport::TYPES as $class) {
            $form = $factory->create($class, null, ['csrf_protection' => false] + ($options[$class] ?? []));
            $html = $template->render(['form' => $form->createView()]);

            self::assertDoesNotMatchRegularExpression('#[>"](?:form|admin)\.[a-z_.]+[<"]#', $html, $class . ' renders a raw translation key.');
        }

        $html = $template->render(['form' => $factory->create(BuilderPageCreateType::class, null, ['csrf_protection' => false])->createView()]);
        self::assertStringContainsString('Page key', $html);
        self::assertMatchesRegularExpression('#<input[^>]+name="builder_page_create\[pageKey\]"[^>]+class="[^"]*nowo-ui-input#', $html);
    }
}
