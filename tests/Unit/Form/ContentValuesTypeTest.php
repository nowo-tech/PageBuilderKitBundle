<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Form;

use Nowo\PageBuilderKitBundle\Form\ContentValuesType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;

#[CoversClass(ContentValuesType::class)]
final class ContentValuesTypeTest extends TestCase
{
    #[Test]
    public function buildsFieldsForAllSupportedTypes(): void
    {
        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidatorBuilder()->getValidator()))
            ->getFormFactory();

        $schema = [
            ['key' => '', 'type' => 'string'],
            [
                'key'           => 'title',
                'type'          => 'string',
                'label'         => 'Title',
                'display_label' => 'Title ES',
                'labels'        => ['es' => 'Título'],
            ],
            ['key' => 'flag', 'type' => 'bool', 'default' => true],
            ['key' => 'tone', 'type' => 'select', 'options' => ['soft', 1, 'loud']],
            ['key' => 'related', 'type' => 'reference'],
            [
                'key'    => 'hero',
                'type'   => 'group',
                'label'  => 'Hero',
                'fields' => [
                    'bad',
                    ['type' => 'string'],
                    ['key' => 'heading', 'type' => 'string'],
                    ['key' => 'url', 'type' => 'url'],
                ],
            ],
            [
                'key'    => 'faqs',
                'type'   => 'repeater',
                'fields' => [
                    'bad',
                    ['type' => 'string'],
                    ['key' => 'question', 'type' => 'string'],
                    ['key' => 'answer', 'type' => 'html'],
                ],
            ],
            ['key' => 'body', 'type' => 'html'],
            ['key' => 'notes', 'type' => 'text'],
            ['key' => 'rich', 'type' => 'richtext'],
            ['key' => 'raw', 'type' => 'raw'],
            ['key' => 'link', 'type' => 'url'],
            ['key' => 'photo', 'type' => 'image'],
            ['key' => 'amount', 'type' => 'number'],
            ['key' => 'icon', 'type' => 'icon', 'label' => 'Icon'],
        ];

        $form = $factory->create(ContentValuesType::class, null, [
            'csrf_protection' => false,
            'schema'          => $schema,
            'locale'          => 'es',
            'values'          => [
                'title'   => 'Hola',
                'flag'    => false,
                'tone'    => 'soft',
                'related' => 'home',
                'hero'    => ['heading' => 'Hi'],
                'faqs'    => [['question' => 'Q1', 'answer' => '<p>A</p>']],
                'body'    => '<p>Body</p>',
                'amount'  => '3',
            ],
            'reference_pages' => ['home', 'about'],
        ]);

        self::assertTrue($form->has('fieldValues'));
        self::assertTrue($form->has('fieldLabels'));
        self::assertTrue($form->get('fieldValues')->get('es')->has('title'));
        self::assertTrue($form->get('fieldValues')->get('es')->has('flag'));
        self::assertTrue($form->get('fieldValues')->get('es')->has('tone'));
        self::assertTrue($form->get('fieldValues')->get('es')->has('related'));
        self::assertTrue($form->get('fieldValues')->get('es')->has('hero'));
        self::assertTrue($form->get('fieldValues')->get('es')->has('faqs'));
        self::assertTrue($form->get('fieldValues')->get('es')->has('body'));
        self::assertTrue($form->get('fieldValues')->get('es')->has('amount'));
        self::assertTrue($form->get('fieldValues')->get('es')->has('icon'));
        self::assertSame('Título', $form->get('fieldLabels')->get('es')->get('title')->getData());

        $fallbackLabel = $factory->create(ContentValuesType::class, null, [
            'csrf_protection' => false,
            'schema'          => [[
                'key'           => 'cta',
                'type'          => 'string',
                'display_label' => 'Call to action',
                'label'         => 'CTA',
            ]],
            'locale' => 'es',
            'values' => [],
        ]);
        self::assertSame('Call to action', $fallbackLabel->get('fieldLabels')->get('es')->get('cta')->getData());
        self::assertInstanceOf(ContentValuesType::class, $form->getConfig()->getType()->getInnerType());
    }
}
