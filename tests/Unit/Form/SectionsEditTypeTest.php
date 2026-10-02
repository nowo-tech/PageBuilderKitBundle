<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Form;

use Nowo\PageBuilderKitBundle\Form\SectionsEditType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;

#[CoversClass(SectionsEditType::class)]
final class SectionsEditTypeTest extends TestCase
{
    #[Test]
    public function buildsWidgetFieldsFromSectionsTree(): void
    {
        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidatorBuilder()->getValidator()))
            ->getFormFactory();

        $tree = [
            ['id' => 'skip-no-columns'],
            [
                'id'      => 'sec',
                'columns' => [
                    ['id' => 'skip-no-widgets'],
                    [
                        'id'      => 'col',
                        'widgets' => [
                            ['fields' => ['title']],
                            [
                                'id'     => 'w1',
                                'fields' => ['title', '', 1, 'html', 'long'],
                                'props'  => [
                                    'title' => 'Hello',
                                    'html'  => '<p>Body</p>',
                                    'long'  => str_repeat('x', 81),
                                    'num'   => 5,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $form = $factory->create(SectionsEditType::class, null, [
            'csrf_protection' => false,
            'sections_tree'   => $tree,
        ]);

        self::assertTrue($form->has('widgets'));
        self::assertTrue($form->get('widgets')->has('w1'));
        self::assertTrue($form->get('widgets')->get('w1')->has('title'));
        self::assertTrue($form->get('widgets')->get('w1')->has('html'));
        self::assertTrue($form->get('widgets')->get('w1')->has('long'));
        self::assertSame('Hello', $form->get('widgets')->get('w1')->get('title')->getData());
        self::assertInstanceOf(SectionsEditType::class, $form->getConfig()->getType()->getInnerType());
    }
}
