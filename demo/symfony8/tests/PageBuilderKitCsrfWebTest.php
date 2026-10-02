<?php

declare(strict_types=1);

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;

use const JSON_THROW_ON_ERROR;

/**
 * HTTP CSRF contracts for Page Builder Kit admin APIs (kernel + real token manager).
 */
final class PageBuilderKitCsrfWebTest extends WebTestCase
{
    public function testDocumentSaveRejectsMissingCsrf(): void
    {
        $client = static::createClient();
        $client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $client->request(
            'POST',
            '/admin/page-builder/pages/home/document',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT'  => 'application/json',
            ],
            content: json_encode([
                'structure'           => [
                    'version'       => 2,
                    'engine'        => 'grapesjs',
                    'html'          => '',
                    'css'           => '',
                    'grapes'        => [],
                    'localeContent' => [],
                    'sections'      => [],
                ],
                'widgetPropsByLocale' => ['en' => [], 'es' => []],
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['error' => 'invalid_csrf'], json_decode((string) $client->getResponse()->getContent(), true));
    }

    public function testDocumentSaveAcceptsValidCsrf(): void
    {
        $client = static::createClient();
        $client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));
        $token = $this->csrfTokenFromPage($client, '/admin/page-builder/pages', 'input[name="_csrf_token"]');

        $client->request(
            'POST',
            '/admin/page-builder/pages/home/document',
            server: [
                'CONTENT_TYPE'      => 'application/json',
                'HTTP_ACCEPT'       => 'application/json',
                'HTTP_X_CSRF_TOKEN' => $token,
            ],
            content: json_encode([
                'structure'           => [
                    'version'       => 2,
                    'engine'        => 'grapesjs',
                    'html'          => '<p data-pbk-csrf-webtest>ok</p>',
                    'css'           => '',
                    'grapes'        => [],
                    'localeContent' => [],
                    'sections'      => [],
                    'fields'        => [],
                    'fieldValues'   => [],
                ],
                'widgetPropsByLocale' => ['en' => [], 'es' => []],
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        self::assertSame(['ok' => true], json_decode((string) $client->getResponse()->getContent(), true));
    }

    public function testContentFieldSaveRejectsMissingCsrf(): void
    {
        $client = static::createClient();
        $client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $client->request(
            'POST',
            '/admin/page-builder/pages/fields/fields/hero_title',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT'  => 'application/json',
            ],
            content: json_encode([
                'locale' => 'en',
                'type'   => 'string',
                'label'  => 'Hero',
                'value'  => 'Nope',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['error' => 'invalid_csrf'], json_decode((string) $client->getResponse()->getContent(), true));
    }

    public function testContentFieldSaveAcceptsValidCsrf(): void
    {
        $client = static::createClient();
        $client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));
        $token = $this->csrfTokenFromPage(
            $client,
            '/admin/page-builder/pages/fields/content',
            'input[name="_csrf_token"]',
        );

        $client->request(
            'POST',
            '/admin/page-builder/pages/fields/fields/hero_title',
            server: [
                'CONTENT_TYPE'      => 'application/json',
                'HTTP_ACCEPT'       => 'application/json',
                'HTTP_X_CSRF_TOKEN' => $token,
            ],
            content: json_encode([
                'locale' => 'en',
                'type'   => 'string',
                'label'  => 'Hero',
                'value'  => 'CSRF webtest title',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($payload);
        self::assertTrue($payload['ok'] ?? false);
        self::assertSame('hero_title', $payload['fieldKey'] ?? null);
    }

    private function csrfTokenFromPage(KernelBrowser $client, string $path, string $selector): string
    {
        $client->request('GET', $path);
        self::assertResponseIsSuccessful();

        $token = $client->getCrawler()->filter($selector)->attr('value');
        self::assertNotEmpty($token);

        return (string) $token;
    }
}
