<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Nowo\PageBuilderKitBundle\DependencyInjection\NowoPageBuilderKitExtension;
use Nowo\PageBuilderKitBundle\Form\BuilderPageCreateType;
use Nowo\PageBuilderKitBundle\Tests\Support\FormKitTestSupport;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;

use function basename;
use function class_uses;
use function dirname;
use function glob;
use function sort;

/**
 * REQ-TWIG-005 rule 11: every page form type of the bundle is a FormKit Type on the `page_builder_kit` profile.
 */
final class FormKitIntegrationTest extends TestCase
{
    #[Test]
    public function everyFormTypeIsAFormKitTypeOnTheBundleProfile(): void
    {
        $files = glob(dirname(__DIR__, 3) . '/src/Form/*Type.php');
        self::assertNotFalse($files);

        $classes = [];
        foreach ($files as $file) {
            $class = 'Nowo\\PageBuilderKitBundle\\Form\\' . basename($file, '.php');
            self::assertTrue(class_exists($class), $class);
            $classes[] = $class;

            $attributes = new ReflectionClass($class)->getAttributes(FormKitConfig::class);
            self::assertCount(1, $attributes, $class . ' must carry #[FormKitConfig].');
            self::assertSame(NowoPageBuilderKitExtension::FORM_KIT_PROFILE, $attributes[0]->newInstance()->name, $class);
            self::assertContains(FormOptionsTrait::class, class_uses($class), $class . ' must use FormOptionsTrait.');
        }

        $registered = FormKitTestSupport::TYPES;
        sort($classes);
        sort($registered);
        self::assertSame($classes, $registered, 'FormKitTestSupport::TYPES must list every page form type.');
    }

    #[Test]
    public function fieldsGetTheProfileOptionsWithoutConventionKeys(): void
    {
        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(FormKitTestSupport::extension())
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->getFormFactory();

        foreach (FormKitTestSupport::TYPES as $class) {
            $form = $factory->create($class);
            foreach ($this->leaves($form) as $child) {
                $options = $child->getConfig()->getOptions();
                $type    = $child->getConfig()->getType()->getInnerType();
                self::assertSame('NowoPageBuilderKitBundle', $options['translation_domain'], $class . ' ' . $child->getName());
                self::assertStringEndsNotWith('.help', (string) ($options['help'] ?? ''), $class . ' ' . $child->getName());
                self::assertStringEndsNotWith('.placeholder', (string) ($options['attr']['placeholder'] ?? ''), $class . ' ' . $child->getName());
                if ($type instanceof CheckboxType) {
                    self::assertSame('', $options['attr']['class'] ?? '', $class . ' ' . $child->getName());
                }
            }
        }
    }

    #[Test]
    public function textFieldsGetTheProfileClass(): void
    {
        $form = Forms::createFormFactoryBuilder()
            ->addExtension(FormKitTestSupport::extension())
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->getFormFactory()
            ->create(BuilderPageCreateType::class);

        self::assertSame('nowo-ui-input', $form->get('pageKey')->getConfig()->getOption('attr')['class']);
        self::assertSame('form.page_key', $form->get('pageKey')->getConfig()->getOption('label'));
    }

    /**
     * @param FormInterface<mixed> $form
     *
     * @return iterable<FormInterface<mixed>>
     */
    private function leaves(FormInterface $form): iterable
    {
        foreach ($form as $child) {
            if ($child->getConfig()->getCompound()) {
                yield from $this->leaves($child);

                continue;
            }
            yield $child;
        }
    }
}
