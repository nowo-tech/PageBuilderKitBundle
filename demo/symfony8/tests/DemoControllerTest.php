<?php

declare(strict_types=1);

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class DemoControllerTest extends WebTestCase
{
    public function testHomePageIsSuccessful(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('h1');
        self::assertSelectorTextContains('body', 'Page Builder Kit');
    }

    public function testContactPageIsSuccessful(): void
    {
        $client = static::createClient();
        $client->request('GET', '/contact');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('h1');
    }

    public function testLoginPageIsSuccessful(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Admin login');
        self::assertSelectorTextContains('body', 'admin');
    }

    public function testTwigPageRendersProductsWithoutEditPencilForAnonymous(): void
    {
        $client = static::createClient();
        $client->request('GET', '/twig');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Starter plan');
        self::assertSelectorNotExists('.pbk-edit-button');
    }

    public function testTwigPageShowsEditPencilWhenAdminLoggedIn(): void
    {
        $client = static::createClient();
        $client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));
        $client->request('GET', '/twig');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.pbk-edit-button');
        $href = $client->getCrawler()->filter('.pbk-edit-button')->attr('href') ?? '';
        self::assertStringContainsString('/admin/page-builder/pages/twig/canvas', $href);
    }

    public function testRevisionsAdminPageIsAvailableWhenEnabled(): void
    {
        $client = static::createClient();
        $client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));
        // Hit a public use-case first so DemoPageSeeder ensures pages + demo revisions.
        $client->request('GET', '/pricing');
        self::assertResponseIsSuccessful();

        $client->request('GET', '/admin/page-builder/pages/pricing/revisions');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Versions');
        self::assertSelectorTextContains('body', 'Demo version');
        self::assertSelectorExists('a[href*="/revisions/"][href$="/diff"]');
    }

    public function testCanvasExposesUnpublishWhenPageIsPublished(): void
    {
        $client = static::createClient();
        $client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));
        $client->request('GET', '/admin/page-builder/pages/twig/canvas');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-pbk-action="unpublish"]');
        self::assertSelectorExists('[data-pbk-unpublish-url]');
    }

    public function testMultiRenderPageEmbedsSeveralTreesInOneRequest(): void
    {
        $client = static::createClient();
        $client->request('GET', '/multi-render');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-pbk-embed="pricing"]');
        self::assertSelectorExists('[data-pbk-embed="about"]');
        self::assertSelectorExists('[data-pbk-embed="faq"]');
        self::assertSelectorTextContains('body', '3 renders');
    }
}
