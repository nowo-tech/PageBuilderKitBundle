<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Integration\Http;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Service\DocumentService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function assert;
use function dirname;
use function file_put_contents;
use function getmypid;
use function is_file;
use function json_decode;
use function json_encode;
use function mkdir;
use function str_replace;
use function strtoupper;
use function sys_get_temp_dir;
use function unlink;

use const JSON_THROW_ON_ERROR;

/**
 * REQ-TEST-003: the admin and public controllers through a real kernel (routes, forms, Twig, SQLite) with a CSRF double.
 */
abstract class AbstractHttpTestCase extends TestCase
{
    protected const string ADMIN = '/admin/page-builder';

    protected ?AdminHttpKernel $kernel = null;

    protected EntityManagerInterface $em;

    protected DocumentService $documents;

    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        $this->files = [];
    }

    /**
     * @param array<string, mixed> $bundle
     */
    protected function boot(array $bundle = []): ContainerInterface
    {
        $this->kernel = new AdminHttpKernel($bundle, static::class . '|' . $this->name());
        @mkdir(dirname($this->kernel->databaseFile()), 0777, true);
        if (is_file($this->kernel->databaseFile())) {
            unlink($this->kernel->databaseFile());
        }
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $em = $container->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        new SchemaTool($em)->createSchema($em->getMetadataFactory()->getAllMetadata());
        $this->em  = $em;
        $documents = $container->get(DocumentService::class);
        self::assertInstanceOf(DocumentService::class, $documents);
        $this->documents = $documents;

        return $container;
    }

    /**
     * @param array<string, mixed> $parameters
     * @param array<string, string> $headers
     * @param array<string, mixed>|null $json
     * @param array<string, mixed> $files
     */
    protected function request(string $method, string $uri, array $parameters = [], array $headers = [], ?array $json = null, array $files = []): Response
    {
        assert($this->kernel instanceof AdminHttpKernel);
        $server = [];
        foreach ($headers as $name => $value) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }
        $content = null;
        if ($json !== null) {
            $content                = json_encode($json, JSON_THROW_ON_ERROR);
            $server['CONTENT_TYPE'] = 'application/json';
        }
        $request = Request::create($uri, $method, $parameters, [], $files, $server, $content);

        return $this->kernel->handle($request);
    }

    /**
     * @return array<string, mixed>
     */
    protected function decode(Response $response): array
    {
        self::assertInstanceOf(JsonResponse::class, $response);

        return json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    protected static function grapes(string $html = '<p>Hi</p>'): array
    {
        return ['version' => 2, 'engine' => 'grapesjs', 'html' => $html, 'css' => '', 'grapes' => [], 'localeContent' => [], 'fields' => [], 'fieldValues' => []];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function classic(): array
    {
        return ['version' => 1, 'sections' => [[
            'id'      => 'section-1',
            'columns' => [[
                'id'       => 'col-1',
                'settings' => ['width' => 12],
                'widgets'  => [
                    ['id' => 'w-head', 'type' => 'heading', 'children' => []],
                    ['id' => 'w-box', 'type' => 'container', 'children' => [['id' => 'w-text', 'type' => 'text', 'children' => []]]],
                ],
            ]],
            'settings' => ['cssId' => 'hero'],
        ]]];
    }

    /**
     * @param array<string, mixed>|null $structure
     */
    protected function page(string $key, ?array $structure = null, bool $publish = false): BuilderPage
    {
        $page = $this->documents->createPage($key, ucfirst($key), 'en');
        if ($structure !== null) {
            $this->documents->saveDocument($page, $structure, []);
        }
        if ($publish) {
            $this->documents->publish($page);
        }

        return $page;
    }

    protected function tmpFile(string $name, string $content): string
    {
        $path = sys_get_temp_dir() . '/pbk-http-' . getmypid() . '-' . $name;
        file_put_contents($path, $content);
        $this->files[] = $path;

        return $path;
    }
}
