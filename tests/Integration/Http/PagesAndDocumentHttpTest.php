<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Integration\Http;

use Symfony\Component\HttpFoundation\Response;

use const JSON_THROW_ON_ERROR;

/**
 * REQ-TEST-003: page list, canvas, document API and public render.
 */
final class PagesAndDocumentHttpTest extends AbstractHttpTestCase
{
    public function testTheListCreatesPagesAndPaginates(): void
    {
        $this->boot();
        $this->page('alpha', self::grapes());
        $this->page('legacy', self::classic());

        $list = $this->request('GET', self::ADMIN . '/pages');
        self::assertSame(200, $list->getStatusCode());
        self::assertStringContainsString('alpha', (string) $list->getContent());
        self::assertSame(200, $this->request('GET', self::ADMIN . '/pages?page=9')->getStatusCode(), 'a page past the end is clamped');

        $created = $this->request('POST', self::ADMIN . '/pages', ['builder_page_create' => ['pageKey' => 'fresh', 'title' => 'Fresh', '_token' => 'accepted']]);
        self::assertSame(302, $created->getStatusCode(), (string) $created->getContent());
        self::assertStringContainsString('/pages/fresh/canvas', (string) $created->headers->get('Location'));

        $duplicate = $this->request('POST', self::ADMIN . '/pages', ['builder_page_create' => ['pageKey' => 'fresh', 'title' => 'Again', '_token' => 'accepted']]);
        self::assertSame(302, $duplicate->getStatusCode());
        self::assertStringEndsWith('/pages', (string) $duplicate->headers->get('Location'), 'a taken key goes back to the list with a flash');

        $invalid = $this->request('POST', self::ADMIN . '/pages', ['builder_page_create' => ['pageKey' => 'Not Valid!', 'title' => '', '_token' => 'accepted']]);
        self::assertSame(422, $invalid->getStatusCode());
    }

    public function testTheCanvasRendersEveryEngineAndTheContentFieldCatalog(): void
    {
        $this->boot(['grapesjs' => ['assets_upload' => ['enabled' => true, 'storage' => 'local', 'local' => ['directory' => sys_get_temp_dir() . '/pbk-http-up-' . getmypid()]]]]);
        $grapes           = self::grapes();
        $grapes['fields'] = [
            ['key' => 'title', 'type' => 'string', 'label' => 'Title'],
            ['key' => 'hero', 'type' => 'image', 'label' => 'Hero'],
            ['key' => 'faq', 'type' => 'repeater', 'label' => 'FAQ', 'fields' => [['key' => 'q', 'type' => 'string', 'label' => 'Q']]],
            ['key' => 'cta', 'type' => 'group', 'label' => 'CTA', 'fields' => [['key' => 'text', 'type' => 'string', 'label' => 'Text']]],
            ['key' => 'empty_group', 'type' => 'group', 'label' => 'Empty', 'fields' => []],
        ];
        $this->page('alpha', $grapes);
        $this->page('legacy', self::classic());
        $this->page('blank');

        foreach (['alpha', 'legacy', 'blank'] as $key) {
            self::assertSame(200, $this->request('GET', self::ADMIN . '/pages/' . $key . '/canvas')->getStatusCode(), $key);
        }
        $body = (string) $this->request('GET', self::ADMIN . '/pages/alpha/canvas')->getContent();
        self::assertStringContainsString('fields.faq', $body);
        self::assertSame(404, $this->request('GET', self::ADMIN . '/pages/ghost/canvas')->getStatusCode());
    }

    public function testTheDocumentApiReadsAndSaves(): void
    {
        $this->boot();
        $this->page('alpha', self::grapes());
        $uri = self::ADMIN . '/pages/alpha/document';

        $document = $this->decode($this->request('GET', $uri));
        self::assertSame('alpha', $document['pageKey']);
        self::assertSame(404, $this->request('GET', self::ADMIN . '/pages/ghost/document')->getStatusCode());
        $blank = $this->page('blank');
        self::assertSame(200, $this->request('GET', self::ADMIN . '/pages/' . $blank->getPageKey() . '/document')->getStatusCode());

        self::assertSame(403, $this->request('POST', $uri, [], ['X-CSRF-TOKEN' => 'rejected'], ['structure' => self::grapes(), 'widgetPropsByLocale' => []])->getStatusCode());
        self::assertSame(404, $this->request('POST', self::ADMIN . '/pages/ghost/document', [], ['X-CSRF-TOKEN' => 'x'], ['structure' => self::grapes(), 'widgetPropsByLocale' => []])->getStatusCode());
        self::assertSame(400, $this->request('POST', $uri, [], ['X-CSRF-TOKEN' => 'x'], ['structure' => 'no'])->getStatusCode());
        self::assertSame(400, $this->request('POST', $uri, [], ['X-CSRF-TOKEN' => 'x'], ['structure' => ['sections' => [['id' => 's', 'columns' => [['widgets' => [['id' => 'a', 'type' => 'nope']]]]]]], 'widgetPropsByLocale' => []])->getStatusCode());
        self::assertSame(200, $this->request('POST', $uri, [], ['X-CSRF-TOKEN' => 'x'], ['structure' => self::grapes('<p>Saved</p>'), 'widgetPropsByLocale' => []])->getStatusCode());
        self::assertSame(200, $this->request('POST', $uri, ['_csrf_token' => 'x'], [], ['structure' => self::grapes(), 'widgetPropsByLocale' => []])->getStatusCode(), 'the token may come from the body');
    }

    public function testPublishUnpublishAndDuplicateAnswerJsonAndRedirects(): void
    {
        $this->boot();
        $this->page('alpha', self::grapes());
        $json = ['Accept' => 'application/json'];
        $base = self::ADMIN . '/pages/alpha';

        self::assertSame(403, $this->request('POST', $base . '/publish', [], ['X-CSRF-TOKEN' => 'rejected'] + $json)->getStatusCode());
        self::assertSame(302, $this->request('POST', $base . '/publish', ['_csrf_token' => 'rejected'])->getStatusCode(), 'HTML clients are sent back to the list');
        self::assertSame(404, $this->request('POST', self::ADMIN . '/pages/ghost/publish', ['_csrf_token' => 'x'], $json)->getStatusCode());
        self::assertSame('published', $this->decode($this->request('POST', $base . '/publish', ['_csrf_token' => 'x'], $json))['status']);
        self::assertSame('draft', $this->decode($this->request('POST', $base . '/unpublish', ['_csrf_token' => 'x'], $json))['status']);
        self::assertSame(403, $this->request('POST', $base . '/unpublish', [], ['X-CSRF-TOKEN' => 'rejected'] + $json)->getStatusCode());
        self::assertSame(404, $this->request('POST', self::ADMIN . '/pages/ghost/unpublish', ['_csrf_token' => 'x'], $json)->getStatusCode());

        $html = $this->request('POST', $base . '/publish', ['_csrf_token' => 'x', '_redirect' => '/somewhere/else']);
        self::assertSame('/somewhere/else', $html->headers->get('Location'));
        foreach (['//evil.example', '/\\evil', 'https://evil.example', "/ctl\x01"] as $unsafe) {
            $response = $this->request('POST', $base . '/unpublish', ['_csrf_token' => 'x', '_redirect' => $unsafe]);
            self::assertStringContainsString('/pages/alpha/canvas', (string) $response->headers->get('Location'), $unsafe);
        }

        self::assertSame(403, $this->request('POST', $base . '/duplicate', [], ['X-CSRF-TOKEN' => 'rejected'] + $json)->getStatusCode());
        self::assertSame(404, $this->request('POST', self::ADMIN . '/pages/ghost/duplicate', ['_csrf_token' => 'x'], $json)->getStatusCode());
        self::assertSame('alpha-2', $this->decode($this->request('POST', $base . '/duplicate', ['_csrf_token' => 'x'], $json, ['pageKey' => 'alpha-2', 'title' => 'Second']))['pageKey']);
        self::assertSame(400, $this->request('POST', $base . '/duplicate', ['_csrf_token' => 'x'], $json, ['pageKey' => 'alpha-2'])->getStatusCode(), 'the key is taken');
        $redirect = $this->request('POST', $base . '/duplicate', ['_csrf_token' => 'x', 'pageKey' => 'alpha-3', 'title' => 'Third']);
        self::assertStringContainsString('/pages/alpha-3/canvas', (string) $redirect->headers->get('Location'));
        self::assertSame(302, $this->request('POST', $base . '/duplicate', ['_csrf_token' => 'x'])->getStatusCode(), 'the default key is <key>-copy');
    }

    public function testExportAndImport(): void
    {
        $this->boot();
        $this->page('alpha', self::grapes());
        $json = ['Accept' => 'application/json'];

        $payload = $this->decode($this->request('GET', self::ADMIN . '/pages/alpha/export'));
        self::assertNotEmpty($payload);
        self::assertSame(404, $this->request('GET', self::ADMIN . '/pages/ghost/export')->getStatusCode());

        self::assertSame(403, $this->request('POST', self::ADMIN . '/pages/import', [], ['X-CSRF-TOKEN' => 'rejected'] + $json)->getStatusCode());
        $imported = $this->request('POST', self::ADMIN . '/pages/import', ['_csrf_token' => 'x'], $json, $payload + ['targetPageKey' => 'imported']);
        self::assertSame(200, $imported->getStatusCode(), (string) $imported->getContent());
        self::assertSame('imported', $this->decode($imported)['pageKey']);
        $published = $this->request('POST', self::ADMIN . '/pages/import', ['_csrf_token' => 'x'], $json, $payload + ['targetPageKey' => 'imported-live', 'publish' => true]);
        self::assertSame(200, $published->getStatusCode(), (string) $published->getContent());

        $form = $this->request('POST', self::ADMIN . '/pages/import', ['_csrf_token' => 'x', 'payload' => json_encode($payload + ['targetPageKey' => 'imported-form'], JSON_THROW_ON_ERROR)]);
        self::assertSame(302, $form->getStatusCode());
        self::assertStringEndsWith('/pages', (string) $form->headers->get('Location'));
        self::assertSame(400, $this->request('POST', self::ADMIN . '/pages/import', ['_csrf_token' => 'x'], $json, ['nonsense' => true])->getStatusCode());
        self::assertSame(302, $this->request('POST', self::ADMIN . '/pages/import', ['_csrf_token' => 'x', 'payload' => '"scalar"'])->getStatusCode());
    }

    public function testThePublicRendererServesPublishedPagesAndDraftPreviews(): void
    {
        $this->boot();
        $this->page('live', self::grapes('<p>Live</p>'), true);
        $this->page('wip', self::grapes('<p>Draft</p>'));
        $this->page('legacy', self::classic(), true);

        self::assertSame(200, $this->request('GET', '/p/live')->getStatusCode());
        $preview = $this->request('GET', '/p/wip');
        self::assertSame(200, $preview->getStatusCode(), 'an authorised admin previews the draft');
        self::assertSame(200, $this->request('GET', '/p/legacy')->getStatusCode());
        self::assertSame(404, $this->request('GET', '/p/ghost')->getStatusCode());
    }

    public function testWithoutAdminAccessADraftIsNotFoundAndTheAdminIsForbidden(): void
    {
        $this->boot(['security' => ['allow_unauthenticated' => false, 'access_roles' => ['ROLE_PB_ADMIN']]]);
        $this->page('live', self::grapes(), true);
        $this->page('wip', self::grapes());

        self::assertSame(200, $this->request('GET', '/p/live')->getStatusCode());
        self::assertSame(404, $this->request('GET', '/p/wip')->getStatusCode());
        self::assertContains($this->request('GET', self::ADMIN . '/pages')->getStatusCode(), [401, 403, 302]);
    }

    public function testAMissingCsrfTokenIsRejectedByEveryApi(): void
    {
        $this->boot();
        $this->page('alpha', self::grapes());

        self::assertSame(Response::HTTP_FORBIDDEN, $this->request('POST', self::ADMIN . '/pages/alpha/document', [], ['X-CSRF-TOKEN' => 'rejected'], ['structure' => [], 'widgetPropsByLocale' => []])->getStatusCode());
    }
}
