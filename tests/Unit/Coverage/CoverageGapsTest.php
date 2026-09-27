<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Coverage;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Nowo\PageBuilderKitBundle\DependencyInjection\Compiler\TwigPathsPass;
use Nowo\PageBuilderKitBundle\DependencyInjection\NowoPageBuilderKitExtension;
use Nowo\PageBuilderKitBundle\Entity\BuilderDocument;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Enum\HtmlSanitizeStrategy;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocales;
use Nowo\PageBuilderKitBundle\Media\AwsS3AssetStorage;
use Nowo\PageBuilderKitBundle\Media\LocalFilesystemAssetStorage;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRepositoryInterface;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtection;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtectionConfig;
use Nowo\PageBuilderKitBundle\Service\DocumentNormalizer;
use Nowo\PageBuilderKitBundle\Service\DocumentService;
use Nowo\PageBuilderKitBundle\Service\ElementAppearanceNormalizer;
use Nowo\PageBuilderKitBundle\Service\GrapesDocumentSanitizer;
use Nowo\PageBuilderKitBundle\Service\GrapesTwigContextProviderInterface;
use Nowo\PageBuilderKitBundle\Service\GrapesTwigRenderer;
use Nowo\PageBuilderKitBundle\Service\PageRenderProvider;
use Nowo\PageBuilderKitBundle\Service\WidgetPropsMerger;
use Nowo\PageBuilderKitBundle\Tests\Support\WidgetTypesFixture;
use Nowo\PageBuilderKitBundle\Widget\AbstractWidgetType;
use Nowo\PageBuilderKitBundle\Widget\Type\ContainerWidgetType;
use Nowo\PageBuilderKitBundle\Widget\Type\HeadingWidgetType;
use Nowo\PageBuilderKitBundle\Widget\Type\HtmlWidgetType;
use Nowo\PageBuilderKitBundle\Widget\Type\ImageWidgetType;
use Nowo\PageBuilderKitBundle\Widget\Type\SpacerWidgetType;
use Nowo\PageBuilderKitBundle\Widget\Type\TextWidgetType;
use Nowo\PageBuilderKitBundle\Widget\WidgetTypeRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function array_column;
use function base64_decode;
use function count;
use function file_put_contents;
use function strtolower;
use function sys_get_temp_dir;
use function uniqid;

use const UPLOAD_ERR_NO_FILE;

#[CoversClass(DocumentService::class)]
#[CoversClass(DocumentNormalizer::class)]
#[CoversClass(ElementAppearanceNormalizer::class)]
#[CoversClass(AwsS3AssetStorage::class)]
#[CoversClass(LocalFilesystemAssetStorage::class)]
#[CoversClass(GrapesTwigRenderer::class)]
#[CoversClass(GrapesDocumentSanitizer::class)]
#[CoversClass(PageRenderProvider::class)]
#[CoversClass(TwigPathsPass::class)]
#[CoversClass(NowoPageBuilderKitExtension::class)]
#[CoversClass(SpacerWidgetType::class)]
#[CoversClass(ImageWidgetType::class)]
#[CoversClass(TextWidgetType::class)]
#[CoversClass(HtmlWidgetType::class)]
#[CoversClass(AbstractWidgetType::class)]
#[CoversClass(BuilderPage::class)]
final class CoverageGapsTest extends TestCase
{
    #[Test]
    public function widgetLabelKeysAndDefaultSanitizeMergedProps(): void
    {
        self::assertSame('widget.spacer.label', (new SpacerWidgetType())->getLabelKey());
        self::assertSame('widget.image.label', (new ImageWidgetType())->getLabelKey());
        self::assertSame('widget.text.label', (new TextWidgetType())->getLabelKey());
        self::assertSame('widget.html.label', (new HtmlWidgetType())->getLabelKey());

        $widget = new class extends AbstractWidgetType {
            public function getType(): string
            {
                return 'plain';
            }

            public function getLabelKey(): string
            {
                return 'plain';
            }

            public function defaultProps(): array
            {
                return ['x' => 1];
            }
        };

        $protection = new PageBuilderProtection(
            new PageBuilderProtectionConfig(HtmlSanitizeStrategy::None, null),
        );
        self::assertSame(['x' => 1, 'y' => 2], $widget->sanitizeProps(['y' => 2], $protection));
    }

    #[Test]
    public function setDocumentRelinksWhenDocumentBelongsToAnotherPage(): void
    {
        $pageA    = new BuilderPage();
        $pageB    = new BuilderPage();
        $document = new BuilderDocument();
        $document->setPage($pageA);

        $pageB->setDocument($document);

        self::assertSame($pageB, $document->getPage());
        self::assertSame($document, $pageB->getDocument());
    }

    #[Test]
    public function twigPathsPassFollowsMultiHopAlias(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $target = new Definition('Twig\Loader\FilesystemLoader');
        $container->setDefinition('twig.loader.native_filesystem', $target);
        $container->setAlias('twig.loader.chain', 'twig.loader.native_filesystem');
        $container->setAlias('twig.loader.native', 'twig.loader.chain');

        (new TwigPathsPass())->process($container);

        self::assertNotEmpty($target->getMethodCalls());
    }

    #[Test]
    public function securityBundleUnavailableWithoutBundlesParameter(): void
    {
        $method = new ReflectionMethod(NowoPageBuilderKitExtension::class, 'isSecurityBundleAvailable');
        $method->setAccessible(true);

        self::assertFalse($method->invoke(new NowoPageBuilderKitExtension(), new ContainerBuilder()));
    }

    #[Test]
    public function grapesTwigCatalogAndSanitizerEdgeCases(): void
    {
        $renderer = new GrapesTwigRenderer(true);
        self::assertTrue($renderer->isEnabled());
        self::assertNotEmpty($renderer->catalogVariables());

        $sanitizer = new GrapesDocumentSanitizer(true);
        self::assertSame('', $sanitizer->sanitizeHtml(''));
        self::assertSame('', $sanitizer->sanitizeCss(''));
        self::assertStringContainsString('ok', $sanitizer->sanitizeHtml('<p onclick="x">ok</p>'));
        $sanitizer->sanitizeHtml("\x00");
    }

    #[Test]
    public function documentNormalizerClassicEdgeBranches(): void
    {
        $normalizer = new DocumentNormalizer();
        $result     = $normalizer->normalize([
            'version'  => 99,
            'sections' => 'not-array',
        ]);
        self::assertSame(DocumentNormalizer::SCHEMA_VERSION, $result['version']);
        self::assertSame([], $result['sections']);

        $result = $normalizer->normalize([
            'sections' => [
                [
                    'id'      => 's1',
                    'columns' => [
                        'bad',
                        [
                            'id'       => 'c1',
                            'settings' => [],
                            'widgets'  => 'bad',
                        ],
                        [
                            'id'      => 'c2',
                            'widgets' => [
                                'skip',
                                [
                                    'id'       => 'w1',
                                    'type'     => 'heading',
                                    'children' => [
                                        ['type' => ''],
                                        [
                                            'id'   => 'w2',
                                            'type' => 'text',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'skip-section',
            ],
        ]);
        self::assertCount(1, $result['sections']);
        self::assertGreaterThanOrEqual(1, count($result['sections'][0]['columns']));

        $result = $normalizer->normalize([
            'sections' => [
                ['id' => 's2', 'columns' => ['x', 'y']],
            ],
        ]);
        self::assertCount(1, $result['sections'][0]['columns']);
        self::assertSame(12, $result['sections'][0]['columns'][0]['settings']['width']);

        self::assertNull($normalizer->normalizeWidget(null, 0));
        self::assertNull($normalizer->normalizeWidget(['type' => 'heading'], DocumentNormalizer::MAX_NESTING_DEPTH + 1));
    }

    #[Test]
    public function elementAppearanceEdgeBranches(): void
    {
        $n = new ElementAppearanceNormalizer();
        self::assertSame(':y', $n->toInlineCss(['x' => 1, '' => 'y', 'ok' => '']));
        $attrs = $n->toHtmlAttributes([
            'cssId'      => 'id1',
            'cssClasses' => 'c',
            'style'      => ['marginTop' => '1px'],
            'attributes' => [
                'skip',
                ['name' => '', 'value' => 'x'],
                ['name' => 'id', 'value' => 'hijack'],
                ['name' => 'title', 'value' => 'javascript:alert(1)'],
                ['name' => 'data-x', 'value' => "ok\x00"],
            ],
        ]);
        self::assertSame('id1', $attrs['id']);
        self::assertSame('javascript:alert(1)', $attrs['title']);

        $normalized = $n->normalize([
            'style'      => ['marginTop' => ['bad'], 'paddingTop' => ''],
            'attributes' => [
                ['name' => 1, 'value' => 'x'],
                'skip',
            ],
        ]);
        self::assertArrayNotHasKey('style', $normalized);

        $safe = $n->normalize([
            'attributes' => [
                ['name' => 'title', 'value' => 'javascript:alert(1)'],
                ['name' => 'data-ok', 'value' => 'v'],
            ],
            'style' => ['marginTop' => 'expression(x)', 'paddingTop' => '1px;}</style>'],
        ]);
        $values = array_column($safe['attributes'] ?? [], 'value', 'name');
        self::assertSame('', $values['title'] ?? 'missing');
        self::assertSame('v', $values['data-ok'] ?? 'missing');
        self::assertArrayNotHasKey('marginTop', $safe['style'] ?? []);

        $m = new ReflectionMethod(ElementAppearanceNormalizer::class, 'sanitizeCssValue');
        $m->setAccessible(true);
        self::assertSame('', $m->invoke($n, 'url(http://x.com/$)'));
    }

    #[Test]
    public function documentServiceCollectAndValidateEdgeCases(): void
    {
        $service = $this->documentService();
        self::assertSame([], $service->collectWidgetIds(['sections' => 'x']));
        self::assertSame(['w1'], $service->collectWidgetIds([
            'sections' => [
                'bad',
                [
                    'columns' => [
                        'bad',
                        ['widgets' => [['id' => 'w1', 'type' => 'heading', 'children' => ['x']]]],
                    ],
                ],
            ],
        ]));

        $structure = [
            'version'  => DocumentNormalizer::SCHEMA_VERSION,
            'sections' => [
                [
                    'columns' => [
                        [
                            'widgets' => [
                                [
                                    'id'       => 'c1',
                                    'type'     => 'container',
                                    'children' => [
                                        ['id' => 'h1', 'type' => 'heading', 'children' => []],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $sanitized = $service->sanitizeWidgetPropsForStructure($structure, [
            'h1'      => ['text' => 'Hi'],
            'missing' => ['x' => 1],
        ]);
        self::assertArrayHasKey('h1', $sanitized);

        $this->expectException(InvalidArgumentException::class);
        $service->validateStructure([
            'version'  => DocumentNormalizer::SCHEMA_VERSION,
            'sections' => [
                [
                    'columns' => [
                        [
                            'widgets' => [
                                ['id' => 'h1', 'type' => 'heading', 'children' => [['id' => 'x', 'type' => 'text']]],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    #[Test]
    public function documentServiceRejectsDeepNestingAndBadChildren(): void
    {
        $service = $this->documentService();
        $node    = ['id' => 'w', 'type' => 'container', 'children' => []];
        for ($i = 0; $i < DocumentNormalizer::MAX_NESTING_DEPTH + 2; ++$i) {
            $node = ['id' => 'c' . $i, 'type' => 'container', 'children' => [$node]];
        }

        try {
            $service->validateStructure([
                'version'  => DocumentNormalizer::SCHEMA_VERSION,
                'sections' => [['columns' => [['widgets' => [$node]]]]],
            ]);
            self::fail('Expected nesting exception');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('nesting', strtolower($e->getMessage()));
        }

        $this->expectException(InvalidArgumentException::class);
        $service->validateStructure([
            'version'  => DocumentNormalizer::SCHEMA_VERSION,
            'sections' => [['columns' => [['widgets' => [
                ['id' => 'c', 'type' => 'container', 'children' => 'bad'],
            ]]]]],
        ]);
    }

    #[Test]
    public function documentServiceSaveDocumentCoercesNonArrayLocaleProps(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');

        $page    = (new BuilderPage())->setPageKey('g');
        $service = $this->documentService(null, $em);

        $mixed = ['es' => 'not-array'];
        // Runtime coercion path: DocumentService accepts mixed locale bags and normalizes non-arrays.
        $service->saveDocument($page, [
            'version'  => DocumentNormalizer::SCHEMA_VERSION,
            'sections' => [
                [
                    'columns' => [
                        [
                            'widgets' => [
                                ['id' => 'h1', 'type' => 'heading', 'children' => []],
                            ],
                        ],
                    ],
                ],
            ],
        ], $mixed); // @phpstan-ignore argument.type (intentional invalid bag)

        self::assertInstanceOf(BuilderDocument::class, $page->getDocument());
    }

    #[Test]
    public function coverRemainingPrivateBranchesViaReflection(): void
    {
        $service         = $this->documentService();
        $sanitizeLocales = new ReflectionMethod(DocumentService::class, 'sanitizeGrapesLocaleContent');
        $sanitizeLocales->setAccessible(true);
        $out = $sanitizeLocales->invoke($service, ['localeContent' => 'bad']);
        self::assertSame([], $out['localeContent']);
        $out = $sanitizeLocales->invoke($service, [
            'localeContent' => [
                0    => ['html' => 'x'],
                'es' => 'bad',
                'en' => ['html' => '<b>x</b>', 'css' => 1, 'grapes' => 'x'],
            ],
        ]);
        self::assertArrayHasKey('en', $out['localeContent']);

        $resolve = new ReflectionMethod(DocumentService::class, 'resolveWidgetTypeForId');
        $resolve->setAccessible(true);
        self::assertNull($resolve->invoke($service, ['sections' => 'x'], 'w'));
        self::assertNull($resolve->invoke($service, [
            'sections' => [
                'bad',
                ['columns' => ['bad', ['widgets' => [['id' => 'w1', 'type' => 1]]]]],
            ],
        ], 'w1'));
        self::assertNull($resolve->invoke($service, [
            'sections' => [['columns' => [['widgets' => [
                ['id' => 'c', 'type' => 'container', 'children' => ['x', ['id' => 'nested', 'type' => 'text']]],
            ]]]]],
        ], 'missing'));

        $sanitized = $service->sanitizeWidgetPropsForStructure([
            'sections' => [['columns' => [['widgets' => [
                ['id' => 'w1', 'type' => null],
            ]]]]],
        ], ['w1' => ['a' => 1]]);
        self::assertSame([], $sanitized);

        $sanitizer = new GrapesDocumentSanitizer(false);
        $html      = $sanitizer->sanitizeHtml('<p>hi</p><script>alert(1)</script>');
        self::assertStringNotContainsString('<script', strtolower($html));

        $dir          = sys_get_temp_dir() . '/pbk-lf-' . uniqid('', true);
        $local        = new LocalFilesystemAssetStorage($dir, '/u', 5_000_000, ['image/png', 'text/plain']);
        $resolveLocal = new ReflectionMethod(LocalFilesystemAssetStorage::class, 'resolveMimeType');
        $resolveLocal->setAccessible(true);
        $tmp2 = sys_get_temp_dir() . '/pbk-lf2-' . uniqid('', true);
        file_put_contents($tmp2, 'x');
        $fallbackUpload = new class($tmp2, 'a.txt', 'text/plain', null, true) extends UploadedFile {
            public function getPathname(): string
            {
                return '';
            }

            public function getClientMimeType(): string
            {
                return 'text/plain';
            }
        };
        self::assertSame('text/plain', $resolveLocal->invoke($local, $fallbackUpload));

        $fallbackUpload2 = new class($tmp2, 'a.txt', '', null, true) extends UploadedFile {
            public function getPathname(): string
            {
                return '';
            }

            public function getClientMimeType(): string
            {
                return '';
            }

            public function getMimeType(): string
            {
                return 'text/plain';
            }
        };
        self::assertSame('text/plain', $resolveLocal->invoke($local, $fallbackUpload2));

        $fallbackUpload3 = new class($tmp2, 'a.txt', '', null, true) extends UploadedFile {
            public function getPathname(): string
            {
                return '';
            }

            public function getClientMimeType(): string
            {
                return '';
            }

            public function getMimeType(): ?string
            {
                throw new RuntimeException('boom');
            }
        };
        self::assertSame('', $resolveLocal->invoke($local, $fallbackUpload3));
    }

    #[Test]
    public function awsS3CoversFallbackUrlArrayResult(): void
    {
        $helper = new class {
            /** @return array<string, string> */
            public function uploadFile(string $filePath, string $fileType, string $key, bool $private = false, string $dispositionType = 'inline', ?string $bucket = null): array
            {
                return ['ObjectURL' => 'https://array-object.example/' . $key];
            }

            /** @param array<string, mixed> $config */
            public function getFileURL(string $bucket, string $key, array $config = []): string
            {
                return '';
            }

            /** @return array<string, mixed> */
            public function getConfiguration(): array
            {
                return ['bucket' => 'from-config'];
            }
        };

        $storage = new AwsS3AssetStorage($helper, '', false, 5_000_000, ['image/png']);
        $tmp     = sys_get_temp_dir() . '/pbk-s3c-' . uniqid('', true) . '.png';
        $png     = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);
        self::assertNotFalse($png);
        file_put_contents($tmp, $png);

        $result = $storage->store(new UploadedFile($tmp, 'x', 'image/png', null, true));
        self::assertStringStartsWith('https://array-object.example/', $result->src);

        $helper2 = new class {
            public string $_bucketName = 'b';

            public function uploadFile(string $filePath, string $fileType, string $key, bool $private = false, string $dispositionType = 'inline', ?string $bucket = null): object
            {
                return new class {
                    public function get(string $name): mixed
                    {
                        return null;
                    }
                };
            }

            /** @param array<string, mixed> $config */
            public function getFileURL(string $bucket, string $key, array $config = []): string
            {
                return 'https://fallback/' . $key;
            }
        };
        $tmp2 = sys_get_temp_dir() . '/pbk-s3d-' . uniqid('', true) . '.png';
        file_put_contents($tmp2, $png);
        $r2 = (new AwsS3AssetStorage($helper2, 'f', false, 5_000_000, ['image/png']))
            ->store(new UploadedFile($tmp2, 'n.png', 'image/png', null, true));
        self::assertStringStartsWith('https://fallback/', $r2->src);

        $helper3 = new class {
            public function uploadFile(string $a, string $b, string $c, bool $d = false, string $e = 'inline', ?string $f = null): object
            {
                return new stdClass();
            }

            /** @param array<string, mixed> $config */
            public function getFileURL(string $bucket, string $key, array $config = []): string
            {
                return '';
            }
        };
        $tmp3 = sys_get_temp_dir() . '/pbk-s3e-' . uniqid('', true) . '.png';
        file_put_contents($tmp3, $png);
        try {
            (new AwsS3AssetStorage($helper3, 'f', false, 5_000_000, ['image/png']))
                ->store(new UploadedFile($tmp3, 'n.png', 'image/png', null, true));
            self::fail('Expected bucket resolve failure');
        } catch (RuntimeException $e) {
            self::assertNotSame('', $e->getMessage());
        }
    }

    #[Test]
    public function awsS3RejectsDisallowedMimeAndEmptyUrl(): void
    {
        $helper = new class {
            public string $_bucketName = 'b';

            /** @return array<string, string> */
            public function uploadFile(string $a, string $b, string $c, bool $d = false, string $e = 'inline', ?string $f = null): array
            {
                return [];
            }

            /** @param array<string, mixed> $config */
            public function getFileURL(string $bucket, string $key, array $config = []): string
            {
                return '';
            }
        };

        $storage = new AwsS3AssetStorage($helper, 'f', false, 5_000_000, ['image/png']);
        $tmp     = sys_get_temp_dir() . '/pbk-s3f-' . uniqid('', true) . '.txt';
        file_put_contents($tmp, 'plain');
        try {
            $storage->store(new UploadedFile($tmp, 'n.txt', 'text/plain', null, true));
            self::fail('mime');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('MIME', $e->getMessage());
        }

        $tmp2 = sys_get_temp_dir() . '/pbk-s3g-' . uniqid('', true) . '.png';
        $png  = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);
        self::assertNotFalse($png);
        file_put_contents($tmp2, $png);
        $this->expectException(RuntimeException::class);
        $storage->store(new UploadedFile($tmp2, 'n.png', 'image/png', null, true));
    }

    #[Test]
    public function localFilesystemMimeFallbackAndExtensions(): void
    {
        $dir     = sys_get_temp_dir() . '/pbk-loc-' . uniqid('', true);
        $storage = new LocalFilesystemAssetStorage($dir, '/u/', 5_000_000, [
            'image/jpeg', 'image/gif', 'image/webp', 'image/svg+xml', 'image/png',
        ]);

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);
        self::assertNotFalse($png);

        $tmp = sys_get_temp_dir() . '/pbk-m-' . uniqid('', true);
        file_put_contents($tmp, $png);
        $upload = new class($tmp, 'file', 'image/png', null, true) extends UploadedFile {
            public function getClientOriginalExtension(): string
            {
                return '';
            }
        };
        $result = $storage->store($upload);
        self::assertStringEndsWith('.png', (string) $result->storageKey);

        $method = new ReflectionMethod(LocalFilesystemAssetStorage::class, 'extensionFromMime');
        $method->setAccessible(true);
        $storageRef = new LocalFilesystemAssetStorage($dir, '/u');
        self::assertSame('jpg', $method->invoke($storageRef, 'image/jpeg'));
        self::assertSame('gif', $method->invoke($storageRef, 'image/gif'));
        self::assertSame('webp', $method->invoke($storageRef, 'image/webp'));
        self::assertSame('svg', $method->invoke($storageRef, 'image/svg+xml'));
        self::assertSame('', $method->invoke($storageRef, 'application/octet-stream'));
    }

    #[Test]
    public function localFilesystemRejectsInvalidUpload(): void
    {
        $dir     = sys_get_temp_dir() . '/pbk-loc2-' . uniqid('', true);
        $storage = new LocalFilesystemAssetStorage($dir, '/u', 5_000_000, ['image/png']);
        $tmp     = sys_get_temp_dir() . '/pbk-inv-' . uniqid('', true) . '.png';
        file_put_contents($tmp, 'x');
        $upload = new UploadedFile($tmp, 'x.png', 'image/png', UPLOAD_ERR_NO_FILE, true);
        $this->expectException(InvalidArgumentException::class);
        $storage->store($upload);
    }

    #[Test]
    public function pageRenderProviderTraversableProvidersAndClassicBranches(): void
    {
        $page     = (new BuilderPage())->setPageKey('home')->setUuid('u');
        $document = (new BuilderDocument())->setPage($page)->setStructure([
            'version'  => DocumentNormalizer::SCHEMA_VERSION,
            'sections' => [
                'bad',
                [
                    'id'      => 's1',
                    'columns' => [
                        'bad',
                        [
                            'id'      => 'c1',
                            'widgets' => [
                                ['id' => 'h1', 'type' => 'heading'],
                                ['id' => 'c2', 'type' => 'container', 'children' => [
                                    ['id' => 't1', 'type' => 'text'],
                                    'bad-child',
                                ]],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $document->upsertLocale('es', ['h1' => ['text' => 'H'], 't1' => ['text' => 'T']]);
        $classicSections = $document->getStructure()['sections'];

        $repo = new class($page) implements BuilderPageRepositoryInterface {
            public function __construct(private readonly BuilderPage $page)
            {
            }

            public function findOneByPageKey(string $pageKey): ?BuilderPage
            {
                if ($pageKey !== 'home') {
                    return null;
                }

                return $this->page;
            }

            public function findAllOrdered(): array
            {
                return [];
            }
        };

        $provider = new class implements GrapesTwigContextProviderInterface {
            public function getContext(BuilderPage $page, string $locale): array
            {
                return ['extra' => 'yes'];
            }
        };

        $render = new PageRenderProvider(
            $repo,
            new DocumentNormalizer(),
            new BuilderLocales('es', ['es']),
            WidgetTypesFixture::registry(),
            new PageBuilderProtection(new PageBuilderProtectionConfig(HtmlSanitizeStrategy::None, null)),
            new WidgetPropsMerger(),
            new GrapesDocumentSanitizer(),
            new GrapesTwigRenderer(false),
            twigContextProviders: (static function () use ($provider) {
                yield $provider;
            })(),
        );

        $page->getDocument()?->setStructure([
            'version'       => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
            'engine'        => DocumentNormalizer::ENGINE_GRAPESJS,
            'html'          => '<p>root</p>',
            'css'           => '',
            'grapes'        => [],
            'localeContent' => [
                'es' => ['html' => '<p>es</p>', 'css' => '', 'grapes' => []],
            ],
            'sections' => [],
        ]);
        $tree = $render->getRenderedTree('home', 'en', ['custom' => 1]);
        self::assertSame('grapesjs', $tree['engine']);

        $page->getDocument()?->setStructure([
            'version'  => DocumentNormalizer::SCHEMA_VERSION,
            'sections' => $classicSections,
        ]);
        $document->upsertLocale('es', ['h1' => ['text' => 'H'], 't1' => ['text' => 'T']]);
        $classic = $render->getRenderedTree('home', 'es');
        self::assertNotEmpty($classic['sections']);
    }

    #[Test]
    public function pageRenderClassicSkipsBadNodes(): void
    {
        $page = (new BuilderPage())->setPageKey('p')->setUuid('u');
        (new BuilderDocument())->setPage($page)->setStructure([
            'version'  => DocumentNormalizer::SCHEMA_VERSION,
            'sections' => [
                'bad',
                [
                    'columns' => [
                        'bad',
                        [
                            'widgets' => [
                                'bad',
                                ['id' => 'h1', 'type' => 'heading'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $page->getDocument()?->upsertLocale('es', ['h1' => ['text' => 'H']]);

        $repo = new class($page) implements BuilderPageRepositoryInterface {
            public function __construct(private readonly BuilderPage $page)
            {
            }

            public function findOneByPageKey(string $pageKey): ?BuilderPage
            {
                return $pageKey === '' ? null : $this->page;
            }

            public function findAllOrdered(): array
            {
                return [];
            }
        };

        $render = new PageRenderProvider(
            $repo,
            new DocumentNormalizer(),
            new BuilderLocales('es', ['es']),
            WidgetTypesFixture::registry(),
            new PageBuilderProtection(new PageBuilderProtectionConfig(HtmlSanitizeStrategy::None, null)),
            new WidgetPropsMerger(),
        );
        $tree = $render->getRenderedTree('p', 'es');
        self::assertNotEmpty($tree['sections']);
    }

    private function documentService(
        ?BuilderPage $existing = null,
        ?EntityManagerInterface $em = null,
    ): DocumentService {
        return new DocumentService(
            new class($existing) implements BuilderPageRepositoryInterface {
                public function __construct(private readonly ?BuilderPage $page)
                {
                }

                public function findOneByPageKey(string $pageKey): ?BuilderPage
                {
                    if ($this->page === null || $this->page->getPageKey() !== $pageKey) {
                        return null;
                    }

                    return $this->page;
                }

                public function findAllOrdered(): array
                {
                    return [];
                }
            },
            $em ?? $this->createStub(EntityManagerInterface::class),
            new BuilderLocales('es', ['es', 'en']),
            new DocumentNormalizer(),
            new WidgetTypeRegistry([
                new HeadingWidgetType(),
                new ContainerWidgetType(),
                new TextWidgetType(),
            ]),
            new PageBuilderProtection(new PageBuilderProtectionConfig(HtmlSanitizeStrategy::None, null)),
            new WidgetPropsMerger(),
        );
    }
}
