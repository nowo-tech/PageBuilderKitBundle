<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Integration\Http;

use Symfony\Component\HttpFoundation\File\UploadedFile;

use function base64_decode;
use function sys_get_temp_dir;

use const JSON_THROW_ON_ERROR;

/**
 * REQ-TEST-003: revisions, content fields, sections, SEO, templates and asset upload controllers.
 */
final class RevisionsContentTemplatesHttpTest extends AbstractHttpTestCase
{
    private const string PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    public function testRevisionsAreListedCreatedDiffedAndRestored(): void
    {
        $this->boot();
        $this->page('alpha', self::grapes('<p>One</p>'));
        $legacy = $this->page('legacy', self::classic());
        $json   = ['Accept' => 'application/json'];
        $base   = self::ADMIN . '/pages/alpha';

        self::assertSame(200, $this->request('GET', $base . '/revisions')->getStatusCode());
        self::assertSame(404, $this->request('GET', self::ADMIN . '/pages/ghost/revisions')->getStatusCode());
        self::assertSame(200, $this->request('GET', self::ADMIN . '/pages/legacy/revisions')->getStatusCode());

        self::assertSame(403, $this->request('POST', $base . '/revisions', [], ['X-CSRF-TOKEN' => 'rejected'] + $json)->getStatusCode());
        self::assertSame(302, $this->request('POST', $base . '/revisions', ['_csrf_token' => 'rejected'])->getStatusCode());
        self::assertSame(404, $this->request('POST', self::ADMIN . '/pages/ghost/revisions', ['_csrf_token' => 'x'], $json)->getStatusCode());
        $created = $this->decode($this->request('POST', $base . '/revisions', ['_csrf_token' => 'x'], $json, ['label' => ' First ']));
        self::assertSame('First', $created['revision']['label']);
        self::assertSame(302, $this->request('POST', $base . '/revisions', ['_csrf_token' => 'x', 'label' => ''])->getStatusCode());
        self::assertNull($this->decode($this->request('POST', $base . '/revisions', ['_csrf_token' => 'x'], $json, ['label' => 5]))['revision']['label'], 'a label that is not text is ignored');
        self::assertSame('legacy-rev', $this->decode($this->request('POST', self::ADMIN . '/pages/legacy/revisions', ['_csrf_token' => 'x'], $json, ['label' => 'legacy-rev']))['revision']['label']);
        $id = $created['revision']['id'];

        $listing = $this->decode($this->request('GET', $base . '/revisions.json'));
        self::assertNotEmpty($listing['revisions']);
        self::assertSame(404, $this->request('GET', self::ADMIN . '/pages/ghost/revisions.json')->getStatusCode());

        self::assertTrue($this->decode($this->request('GET', $base . '/revisions/' . $id . '/diff', [], $json))['ok']);
        self::assertSame(200, $this->request('GET', $base . '/revisions/' . $id . '/diff')->getStatusCode());
        self::assertSame(404, $this->request('GET', self::ADMIN . '/pages/ghost/revisions/1/diff', [], $json)->getStatusCode());
        self::assertSame(302, $this->request('GET', self::ADMIN . '/pages/ghost/revisions/1/diff')->getStatusCode());
        self::assertSame(400, $this->request('GET', $base . '/revisions/99999/diff', [], $json)->getStatusCode());

        self::assertSame(403, $this->request('POST', $base . '/revisions/' . $id . '/restore', [], ['X-CSRF-TOKEN' => 'rejected'] + $json)->getStatusCode());
        self::assertSame(404, $this->request('POST', self::ADMIN . '/pages/ghost/revisions/1/restore', ['_csrf_token' => 'x'], $json)->getStatusCode());
        self::assertSame(400, $this->request('POST', $base . '/revisions/99999/restore', ['_csrf_token' => 'x'], $json)->getStatusCode());
        self::assertTrue($this->decode($this->request('POST', $base . '/revisions/' . $id . '/restore', ['_csrf_token' => 'x'], $json))['ok']);
        self::assertStringContainsString('/canvas', (string) $this->request('POST', $base . '/revisions/' . $id . '/restore', ['_csrf_token' => 'x'])->headers->get('Location'));
        $legacyRevision = $this->decode($this->request('GET', self::ADMIN . '/pages/legacy/revisions.json'))['revisions'][0]['id'];
        self::assertStringContainsString('/sections', (string) $this->request('POST', self::ADMIN . '/pages/legacy/revisions/' . $legacyRevision . '/restore', ['_csrf_token' => 'x'])->headers->get('Location'));
        self::assertNotNull($legacy->getId());
    }

    public function testRevisionsDisabledAnswersNotFound(): void
    {
        $this->boot(['revisions' => ['enabled' => false]]);
        $this->page('alpha', self::grapes());

        self::assertSame(404, $this->request('GET', self::ADMIN . '/pages/alpha/revisions')->getStatusCode());
        self::assertSame(404, $this->request('GET', self::ADMIN . '/pages/alpha/revisions.json')->getStatusCode());
    }

    public function testContentFieldsSchemaValuesAndInlineSave(): void
    {
        $this->boot();
        $this->page('alpha', self::grapes());
        $this->page('other', self::grapes());
        $base   = self::ADMIN . '/pages/alpha';
        $schema = $base . '/content/schema';

        self::assertSame(200, $this->request('GET', $base . '/content')->getStatusCode());
        self::assertSame(404, $this->request('GET', self::ADMIN . '/pages/ghost/content')->getStatusCode());
        self::assertSame(302, $this->request('POST', $schema, ['_csrf_token' => 'rejected', 'schema_action' => 'add'])->getStatusCode());
        self::assertSame(404, $this->request('POST', self::ADMIN . '/pages/ghost/content/schema', ['_csrf_token' => 'x'])->getStatusCode());

        foreach ([
            ['key' => 'title', 'type' => 'string', 'label' => 'Title', 'required' => '1'],
            ['key' => 'body', 'type' => 'text', 'label' => ''],
            ['key' => 'flag', 'type' => 'bool', 'label' => 'Flag'],
            ['key' => 'tone', 'type' => 'select', 'label' => 'Tone', 'options' => 'calm, bold,'],
            ['key' => 'related', 'type' => 'reference', 'label' => 'Related'],
            ['key' => 'faq', 'type' => 'repeater', 'label' => 'FAQ', 'subfields' => 'q:string, a:text', 'min' => '1', 'max' => '3'],
            ['key' => 'cta', 'type' => 'group', 'label' => 'CTA', 'subfields' => 'text:string'],
            ['key' => 'loose', 'type' => 'repeater', 'label' => 'Loose', 'subfields' => 'x:string', 'min' => '', 'max' => ''],
        ] as $field) {
            $response = $this->request('POST', $schema, ['_csrf_token' => 'x', 'schema_action' => 'add'] + $field);
            self::assertSame(302, $response->getStatusCode(), $field['key']);
        }
        self::assertSame(302, $this->request('POST', $schema, ['_csrf_token' => 'x', 'schema_action' => 'add', 'key' => 'Bad Key!', 'type' => 'string'])->getStatusCode(), 'an invalid key is flashed');
        self::assertSame(302, $this->request('POST', $schema, ['_csrf_token' => 'x', 'schema_action' => 'remove', 'key' => 'loose'])->getStatusCode());
        self::assertSame(302, $this->request('POST', $schema, ['_csrf_token' => 'x', 'schema_action' => 'noop'])->getStatusCode());

        $page = $this->request('GET', $base . '/content?locale=es');
        self::assertSame(200, $page->getStatusCode());
        self::assertSame(200, $this->request('GET', $base . '/content?locale=xx')->getStatusCode(), 'an unknown locale falls back to the default');

        $save = $base . '/content';
        self::assertSame(302, $this->request('POST', $save, ['action' => 'save_values', '_csrf_token' => 'rejected'])->getStatusCode());
        $saved = $this->request('POST', $save . '?locale=en', [
            'action'      => 'save_values',
            '_csrf_token' => 'x',
            'fieldValues' => ['en' => ['title' => 'Hello', 'body' => 'Body', 'tone' => 'calm'], 'xx' => ['title' => 'ignored'], 'es' => 'not-a-bag'],
            'fieldLabels' => ['en' => ['title' => 'Headline', 'body' => '  ', 'flag' => 'Flag!']],
        ]);
        self::assertSame(302, $saved->getStatusCode());
        $again = $this->request('GET', $base . '/content');
        self::assertStringContainsString('Hello', (string) $again->getContent());
        self::assertSame(302, $this->request('POST', $save, ['action' => 'save_values', '_csrf_token' => 'x', 'fieldValues' => ['en' => ['title' => '']]])->getStatusCode(), 'a required field left blank is flashed');

        $field = $base . '/fields/title';
        self::assertSame(403, $this->request('POST', $field, [], ['X-CSRF-TOKEN' => 'rejected'], ['value' => 'x'])->getStatusCode());
        self::assertSame(404, $this->request('POST', self::ADMIN . '/pages/ghost/fields/title', [], ['X-CSRF-TOKEN' => 'x'], ['value' => 'x'])->getStatusCode());
        self::assertSame(400, $this->request('POST', $field, [], ['X-CSRF-TOKEN' => 'x'], ['value' => 'x', 'locale' => 'xx'])->getStatusCode());
        $ok = $this->decode($this->request('POST', $field, [], ['X-CSRF-TOKEN' => 'x'], [
            'value' => 'Inline', 'locale' => 'es', 'type' => 'string', 'label' => 'Title', 'labels' => ['es' => 'Título', 3 => 'x', 'en' => ''], 'required' => true, 'options' => ['a', '', 7],
        ]));
        self::assertTrue($ok['ok']);
    }

    public function testSectionsOfAClassicPageAndTheRedirectOfAGrapesPage(): void
    {
        $this->boot();
        $this->page('legacy', self::classic());
        $this->page('alpha', self::grapes());
        $uri = self::ADMIN . '/pages/legacy/sections';

        $page = $this->request('GET', $uri);
        self::assertSame(200, $page->getStatusCode());
        self::assertSame(200, $this->request('GET', $uri . '?locale=es')->getStatusCode());
        self::assertSame(200, $this->request('GET', $uri . '?locale=xx')->getStatusCode());
        self::assertSame(404, $this->request('GET', self::ADMIN . '/pages/ghost/sections')->getStatusCode());

        // A stored structure with entries the editor cannot show (hand-edited rows): they are skipped.
        $sloppy   = $this->page('sloppy', self::classic());
        $document = $sloppy->getDocument();
        self::assertNotNull($document);
        $document->setStructure(['version' => 1, 'sections' => ['not-a-section', ['id' => 's-1', 'columns' => ['not-a-column', ['widgets' => ['bogus', ['id' => '', 'type' => 'text'], ['id' => 'w-unknown', 'type' => 'nope'], ['id' => 'w-ok', 'type' => 'container', 'children' => ['x', ['id' => 'w-child', 'type' => 'text']]]]]]], ['settings' => ['cssId' => 'x']]]]);
        $this->em->flush();
        self::assertSame(200, $this->request('GET', self::ADMIN . '/pages/sloppy/sections')->getStatusCode());
        self::assertStringContainsString('/canvas', (string) $this->request('GET', self::ADMIN . '/pages/alpha/sections')->headers->get('Location'));

        $saved = $this->request('POST', $uri . '?locale=es', ['_token' => 'accepted', 'widgets' => ['w-head' => ['text' => 'Hola', 'tag' => 'h3'], 'w-text' => ['html' => '<p>Cuerpo</p>']]]);
        self::assertSame(302, $saved->getStatusCode(), (string) $saved->getContent());
        self::assertStringContainsString('locale=es', (string) $saved->headers->get('Location'));
        self::assertStringContainsString('Hola', (string) $this->request('GET', $uri . '?locale=es')->getContent(), 'the saved props are shown again');
        self::assertSame(422, $this->request('POST', $uri, ['_token' => 'accepted', 'widgets' => ['unknown-widget' => ['x' => 'y']]])->getStatusCode(), 'extra widget fields are refused');
    }

    public function testSeoEditAndSave(): void
    {
        $this->boot();
        $this->page('alpha', self::grapes());
        $uri = self::ADMIN . '/pages/alpha/seo';

        self::assertSame(200, $this->request('GET', $uri)->getStatusCode());
        self::assertSame(200, $this->request('GET', $uri . '?locale=es')->getStatusCode(), 'a locale without a translation gets a blank one');
        self::assertSame(200, $this->request('GET', $uri . '?locale=xx')->getStatusCode());
        self::assertSame(404, $this->request('GET', self::ADMIN . '/pages/ghost/seo')->getStatusCode());

        $saved = $this->request('POST', $uri, ['builder_page_seo' => ['title' => 'Alpha', 'slug' => 'alpha', 'metaTitle' => 'Meta', 'metaDescription' => 'Desc', 'robots' => 'index,follow', '_token' => 'accepted']]);
        self::assertSame(302, $saved->getStatusCode(), (string) $saved->getContent());
        self::assertSame(422, $this->request('POST', $uri, ['builder_page_seo' => ['title' => str_repeat('t', 400), 'slug' => str_repeat('s', 400), '_token' => 'accepted']])->getStatusCode());
    }

    public function testSeoBlankRequiredFieldsAnswer422WithFormErrorNot500(): void
    {
        $this->boot();
        $this->page('alpha', self::grapes());
        $uri = self::ADMIN . '/pages/alpha/seo';

        foreach (['title', 'slug'] as $blank) {
            $fields         = ['title' => 'Alpha', 'slug' => 'alpha', '_token' => 'accepted'];
            $fields[$blank] = '';
            $response       = $this->request('POST', $uri, ['builder_page_seo' => $fields]);
            self::assertSame(422, $response->getStatusCode(), $blank . ': ' . substr((string) $response->getContent(), 0, 300));
            self::assertStringContainsString('should not be blank', (string) $response->getContent());
        }
    }

    public function testTemplatesListSaveApplyExportImportAndDelete(): void
    {
        $this->boot();
        $this->page('alpha', self::grapes());
        $json = ['Accept' => 'application/json'];

        self::assertSame(200, $this->request('GET', self::ADMIN . '/templates')->getStatusCode());
        self::assertSame(403, $this->request('POST', self::ADMIN . '/pages/alpha/templates', [], ['X-CSRF-TOKEN' => 'rejected'] + $json)->getStatusCode());
        self::assertSame(302, $this->request('POST', self::ADMIN . '/pages/alpha/templates', ['_csrf_token' => 'rejected'])->getStatusCode());
        self::assertSame(404, $this->request('POST', self::ADMIN . '/pages/ghost/templates', ['_csrf_token' => 'x'], $json)->getStatusCode());
        self::assertSame(400, $this->request('POST', self::ADMIN . '/pages/alpha/templates', ['_csrf_token' => 'x'], $json, ['templateKey' => 'Bad Key', 'label' => 'x'])->getStatusCode());
        self::assertSame('tpl', $this->decode($this->request('POST', self::ADMIN . '/pages/alpha/templates', ['_csrf_token' => 'x'], $json, ['templateKey' => 'tpl', 'label' => 'Template']))['templateKey']);
        self::assertSame(302, $this->request('POST', self::ADMIN . '/pages/alpha/templates', ['_csrf_token' => 'x', 'templateKey' => 'tpl-two', 'label' => 'Two'])->getStatusCode());
        self::assertSame(200, $this->request('GET', self::ADMIN . '/templates')->getStatusCode());

        $apply = self::ADMIN . '/templates/tpl/apply';
        self::assertSame(403, $this->request('POST', $apply, [], ['X-CSRF-TOKEN' => 'rejected'] + $json)->getStatusCode());
        self::assertSame(400, $this->request('POST', self::ADMIN . '/templates/ghost/apply', ['_csrf_token' => 'x'], $json, ['pageKey' => 'p1'])->getStatusCode());
        self::assertSame('p1', $this->decode($this->request('POST', $apply, ['_csrf_token' => 'x'], $json, ['pageKey' => 'p1', 'title' => 'P1', 'include_field_schema' => true, 'include_field_values' => true, 'include_seo' => true]))['pageKey']);
        self::assertSame(302, $this->request('POST', $apply, ['_csrf_token' => 'x', 'pageKey' => 'p2', 'title' => 'P2', 'include_field_schema' => '1', 'include_field_values' => '1', 'include_seo' => '1'])->getStatusCode());
        self::assertSame(302, $this->request('POST', $apply, ['_csrf_token' => 'x', 'pageKey' => 'p3'])->getStatusCode());

        $all = $this->decode($this->request('GET', self::ADMIN . '/templates/export'));
        self::assertNotEmpty($all['templates']);
        $one = $this->decode($this->request('GET', self::ADMIN . '/templates/tpl/export'));
        self::assertSame(400, $this->request('GET', self::ADMIN . '/templates/ghost/export')->getStatusCode());

        $import = self::ADMIN . '/templates/import';
        self::assertSame(403, $this->request('POST', $import, [], ['X-CSRF-TOKEN' => 'rejected'] + $json)->getStatusCode());
        self::assertSame(400, $this->request('POST', $import, ['_csrf_token' => 'x'], $json, $all + ['overwrite' => false])->getStatusCode(), 'existing templates are not overwritten');
        self::assertTrue($this->decode($this->request('POST', $import, ['_csrf_token' => 'x'], $json, $all + ['overwrite' => true]))['ok']);
        self::assertTrue($this->decode($this->request('POST', $import, ['_csrf_token' => 'x'], $json, $all))['ok']);
        self::assertSame(302, $this->request('POST', $import, ['_csrf_token' => 'x', 'payload' => json_encode($one, JSON_THROW_ON_ERROR), 'overwrite' => '0'])->getStatusCode());
        self::assertSame(302, $this->request('POST', $import, ['_csrf_token' => 'x', 'payload' => 'not json'])->getStatusCode());
        self::assertSame(400, $this->request('POST', $import, ['_csrf_token' => 'x'], $json, ['nonsense' => 1])->getStatusCode());

        $delete = self::ADMIN . '/templates/tpl-two/delete';
        self::assertSame(403, $this->request('POST', $delete, [], ['X-CSRF-TOKEN' => 'rejected'] + $json)->getStatusCode());
        self::assertTrue($this->decode($this->request('POST', $delete, ['_csrf_token' => 'x'], $json))['ok']);
        self::assertSame(400, $this->request('POST', $delete, ['_csrf_token' => 'x'], $json)->getStatusCode());
        self::assertSame(302, $this->request('POST', self::ADMIN . '/templates/tpl/delete', ['_csrf_token' => 'x'])->getStatusCode());
    }

    public function testAssetUploadAndLibrary(): void
    {
        $dir = sys_get_temp_dir() . '/pbk-http-up-' . getmypid();
        $this->boot(['grapesjs' => ['assets_upload' => ['enabled' => true, 'storage' => 'local', 'local' => ['directory' => $dir, 'public_prefix' => '/uploads/pb']]]]);

        self::assertSame(200, $this->request('GET', self::ADMIN . '/assets?limit=0')->getStatusCode());
        self::assertSame(200, $this->request('GET', self::ADMIN . '/assets?limit=9999')->getStatusCode());
        self::assertSame(403, $this->request('POST', self::ADMIN . '/assets/upload', [], ['X-CSRF-TOKEN' => 'rejected'])->getStatusCode());
        self::assertSame(403, $this->request('POST', self::ADMIN . '/assets/upload')->getStatusCode(), 'no token at all');
        self::assertSame(400, $this->request('POST', self::ADMIN . '/assets/upload', [], ['X-CSRF-TOKEN' => 'x'])->getStatusCode(), 'no file');

        $png      = $this->tmpFile('pixel.png', base64_decode(self::PNG));
        $png2     = $this->tmpFile('pixel2.png', base64_decode(self::PNG));
        $uploaded = $this->request('POST', self::ADMIN . '/assets/upload', [], ['X-CSRF-TOKEN' => 'x'], null, ['files' => [new UploadedFile($png, 'pixel.png', 'image/png', null, true)]]);
        self::assertSame(200, $uploaded->getStatusCode(), (string) $uploaded->getContent());
        $single = $this->request('POST', self::ADMIN . '/assets/upload', ['_token' => 'x'], [], null, ['file' => new UploadedFile($png2, 'pixel2.png', 'image/png', null, true)]);
        self::assertSame(200, $single->getStatusCode(), (string) $single->getContent());
        $text = $this->tmpFile('note.txt', 'plain text');
        self::assertSame(400, $this->request('POST', self::ADMIN . '/assets/upload', [], ['X-CSRF-TOKEN' => 'x'], null, ['files' => [new UploadedFile($text, 'note.txt', 'text/plain', null, true)]])->getStatusCode());
        self::assertNotEmpty($this->decode($this->request('GET', self::ADMIN . '/assets'))['data']);
    }

    public function testAssetEndpointsAreNotFoundWhenUploadIsOff(): void
    {
        $this->boot();

        self::assertSame(404, $this->request('GET', self::ADMIN . '/assets')->getStatusCode());
        self::assertSame(404, $this->request('POST', self::ADMIN . '/assets/upload', [], ['X-CSRF-TOKEN' => 'x'])->getStatusCode());
    }
}
