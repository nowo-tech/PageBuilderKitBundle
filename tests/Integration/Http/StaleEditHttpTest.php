<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Integration\Http;

use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageTranslation;

use function html_entity_decode;
use function in_array;
use function json_encode;
use function parse_str;
use function preg_match_all;
use function rawurlencode;
use function str_contains;
use function strlen;
use function strtolower;

use const ENT_HTML5;
use const ENT_QUOTES;
use const JSON_THROW_ON_ERROR;
use const PREG_SET_ORDER;

/**
 * REQ-RUNTIME-003 rule 4 (1.18): a stale editor is refused instead of overwriting another editor's committed save: the
 * canvas and the classic sections editor through the document token, the content form for a field another editor
 * changed to another value, the SEO form field by field.
 */
final class StaleEditHttpTest extends AbstractHttpTestCase
{
    public function testTheCanvasAndTheDocumentApiCarryTheToken(): void
    {
        $this->boot();
        $this->page('alpha', self::grapes('<p>v1</p>'));
        $uri = self::ADMIN . '/pages/alpha/document';

        $canvas = (string) $this->request('GET', self::ADMIN . '/pages/alpha/canvas')->getContent();
        $loaded = $this->decode($this->request('GET', $uri));
        self::assertIsString($loaded['token'] ?? null);
        self::assertStringContainsString('data-pbk-document-token="' . $loaded['token'] . '"', $canvas);

        $saved = $this->decode($this->request('POST', $uri, [], ['X-CSRF-TOKEN' => 'x'], ['structure' => self::grapes('<p>v2</p>'), 'widgetPropsByLocale' => [], 'baseToken' => $loaded['token']]));
        self::assertTrue($saved['ok']);
        self::assertIsString($saved['token']);
        self::assertNotSame($loaded['token'], $saved['token'], 'the answer carries the new token');

        // The first editor's token is stale now: refused, domain code, no X-Conflict-Code, nothing overwritten.
        $stale = $this->request('POST', $uri, [], ['X-CSRF-TOKEN' => 'x'], ['structure' => self::grapes('<p>v3</p>'), 'widgetPropsByLocale' => [], 'baseToken' => $loaded['token']]);
        self::assertSame(409, $stale->getStatusCode());
        self::assertNull($stale->headers->get('X-Conflict-Code'));
        $body = $this->decode($stale);
        self::assertSame('stale_edit', $body['error']);
        self::assertSame('PAGE_BUILDER_STALE_EDIT', $body['code']);
        self::assertSame($saved['token'], $body['token']);
        self::assertSame('<p>v2</p>', $this->decode($this->request('GET', $uri))['structure']['html']);

        // No token (your own client) or an empty one: the save replaces the document as before.
        self::assertSame(200, $this->request('POST', $uri, [], ['X-CSRF-TOKEN' => 'x'], ['structure' => self::grapes('<p>v4</p>'), 'widgetPropsByLocale' => []])->getStatusCode());
        self::assertSame(200, $this->request('POST', $uri, [], ['X-CSRF-TOKEN' => 'x'], ['structure' => self::grapes('<p>v5</p>'), 'widgetPropsByLocale' => [], 'baseToken' => ''])->getStatusCode());
    }

    public function testTheClassicSectionsEditorRefusesAStaleForm(): void
    {
        $this->boot();
        $page = $this->page('legacy', self::classic());
        $uri  = self::ADMIN . '/pages/legacy/sections?locale=es';

        $form  = (string) $this->request('GET', $uri)->getContent();
        $token = $this->inputValue($form, 'base_token');
        self::assertNotSame('', $token);

        // Another editor saves the page after the form was shown.
        $this->documents->saveDocument($page, self::classic(), ['en' => ['w-head' => ['text' => 'Other editor']]]);

        $refused = $this->request('POST', $uri, ['_token' => 'accepted', 'base_token' => $token, 'widgets' => ['w-head' => ['text' => 'Hola', 'tag' => 'h3']]]);
        self::assertSame(302, $refused->getStatusCode(), substr((string) $refused->getContent(), 0, 600));
        self::assertStringNotContainsString('Hola', (string) $this->request('GET', $uri)->getContent(), 'nothing of the stale form is saved');
        self::assertStringContainsString('Other editor', (string) $this->request('GET', self::ADMIN . '/pages/legacy/sections?locale=en')->getContent(), 'the other editor\'s locale is kept');

        $fresh = $this->inputValue((string) $this->request('GET', $uri)->getContent(), 'base_token');
        $saved = $this->request('POST', $uri, ['_token' => 'accepted', 'base_token' => $fresh, 'widgets' => ['w-head' => ['text' => 'Hola', 'tag' => 'h3']]]);
        self::assertSame(302, $saved->getStatusCode(), substr((string) $saved->getContent(), 0, 600));
        self::assertStringContainsString('Hola', (string) $this->request('GET', $uri)->getContent());
    }

    public function testTheContentFormRefusesAFieldAnotherEditorChangedToAnotherValue(): void
    {
        $this->boot();
        $schema = [['key' => 'headline', 'type' => 'string', 'label' => 'Headline'], ['key' => 'lead', 'type' => 'string', 'label' => 'Lead']];
        $page   = $this->page('alpha', [...self::grapes(), 'fields' => $schema, 'fieldValues' => ['en' => ['headline' => 'Old']]]);
        $uri    = self::ADMIN . '/pages/alpha/content?locale=en';

        $fields = $this->formFields((string) $this->request('GET', $uri)->getContent());

        // Another editor changes the headline after the form was shown.
        $this->documents->saveDocument($page, [...self::grapes(), 'fields' => $schema, 'fieldValues' => ['en' => ['headline' => 'Headline of B']]], []);

        $refused = $this->request('POST', $uri, $this->postedFields($fields, ['action' => 'save_values', '_csrf_token' => 'x', 'fieldValues[en][headline]' => 'Headline of A', 'fieldValues[en][lead]' => 'Lead of A']));
        self::assertSame(302, $refused->getStatusCode(), substr((string) $refused->getContent(), 0, 600));
        $stored = $this->storedStructure('alpha');
        self::assertSame('Headline of B', $stored['fieldValues']['en']['headline']);
        self::assertArrayNotHasKey('lead', $stored['fieldValues']['en'], 'the refused save writes nothing');

        // The same form without touching the headline: the lead is saved and the other editor's headline stays.
        $saved = $this->request('POST', $uri, $this->postedFields($fields, ['action' => 'save_values', '_csrf_token' => 'x', 'fieldValues[en][headline]' => 'Old', 'fieldValues[en][lead]' => 'Lead of A']));
        self::assertSame(302, $saved->getStatusCode(), substr((string) $saved->getContent(), 0, 600));
        $stored = $this->storedStructure('alpha');
        self::assertSame('Headline of B', $stored['fieldValues']['en']['headline']);
        self::assertSame('Lead of A', $stored['fieldValues']['en']['lead']);
    }

    public function testTheSeoFormKeepsAnotherEditorsFieldAndRefusesTheSameField(): void
    {
        $this->boot();
        $this->page('alpha', self::grapes());
        $uri = self::ADMIN . '/pages/alpha/seo?locale=en';

        $fields = $this->formFields((string) $this->request('GET', $uri)->getContent());
        $this->sql('alpha', 'metaTitle', 'Meta of B');

        // A changes the title only: the meta title another editor stored stays (the form no longer writes it back).
        $saved = $this->request('POST', $uri, $this->postedFields($fields, ['builder_page_seo[title]' => 'Title of A']));
        self::assertSame(302, $saved->getStatusCode(), substr((string) $saved->getContent(), 0, 600));
        self::assertSame(['Title of A', 'Meta of B'], $this->storedSeo('alpha'));

        // A changes the meta title too: another editor stored another value of that field, the save is refused whole.
        $refused = $this->request('POST', $uri, $this->postedFields($fields, ['builder_page_seo[title]' => 'Title 2 of A', 'builder_page_seo[metaTitle]' => 'Meta of A']));
        self::assertSame(302, $refused->getStatusCode(), substr((string) $refused->getContent(), 0, 600));
        self::assertSame(['Title of A', 'Meta of B'], $this->storedSeo('alpha'));

        // The same value another editor already stored is not a conflict.
        $same = $this->request('POST', $uri, $this->postedFields($fields, ['builder_page_seo[metaTitle]' => 'Meta of B']));
        self::assertSame(302, $same->getStatusCode(), substr((string) $same->getContent(), 0, 600));
        self::assertSame(['Title of A', 'Meta of B'], $this->storedSeo('alpha'));
    }

    public function testTheSeoFormWithoutUsableOriginalsWritesThePostedValues(): void
    {
        $this->boot();
        $this->page('alpha', self::grapes());
        $uri    = self::ADMIN . '/pages/alpha/seo?locale=en';
        $fields = $this->formFields((string) $this->request('GET', $uri)->getContent());
        $this->sql('alpha', 'metaTitle', 'Meta of B');

        foreach (['not json', json_encode(['title' => 5], JSON_THROW_ON_ERROR), ''] as $originals) {
            $this->sql('alpha', 'metaTitle', 'Meta of B');
            $response = $this->request('POST', $uri, $this->postedFields($fields, ['builder_page_seo[originals]' => $originals, 'builder_page_seo[title]' => 'Title ' . strlen($originals), 'builder_page_seo[metaTitle]' => 'Meta of A']));
            self::assertSame(302, $response->getStatusCode(), substr((string) $response->getContent(), 0, 600));
            self::assertSame('Meta of A', $this->storedSeo('alpha')[1], 'a field whose original is unknown is the editor\'s');
        }
    }

    public function testTheSeoFormCreatesANewTranslation(): void
    {
        $this->boot();
        $this->page('alpha', self::grapes());
        $uri    = self::ADMIN . '/pages/alpha/seo?locale=es';
        $fields = $this->formFields((string) $this->request('GET', $uri)->getContent());
        $this->em->clear(); // a request of its own: the blank translation of the GET is not in this one's identity map

        $saved = $this->request('POST', $uri, $this->postedFields($fields, ['builder_page_seo[title]' => 'Alfa', 'builder_page_seo[slug]' => 'alfa']));
        self::assertSame(302, $saved->getStatusCode(), substr((string) $saved->getContent(), 0, 600));
        $this->em->clear();
        $page = $this->em->getRepository(BuilderPage::class)->findOneBy(['pageKey' => 'alpha']);
        self::assertInstanceOf(BuilderPage::class, $page);
        self::assertSame('Alfa', $page->getTranslation('es')?->getTitle());
    }

    /**
     * @return array<string, mixed>
     */
    private function storedStructure(string $pageKey): array
    {
        $this->em->clear();
        $page = $this->em->getRepository(BuilderPage::class)->findOneBy(['pageKey' => $pageKey]);
        self::assertInstanceOf(BuilderPage::class, $page);

        return $page->getDocument()?->getStructure() ?? [];
    }

    /**
     * @return array{0: string, 1: string|null} title and meta title of the English translation
     */
    private function storedSeo(string $pageKey): array
    {
        $this->em->clear();
        $page = $this->em->getRepository(BuilderPage::class)->findOneBy(['pageKey' => $pageKey]);
        self::assertInstanceOf(BuilderPage::class, $page);
        $translation = $page->getTranslation('en');
        self::assertInstanceOf(BuilderPageTranslation::class, $translation);

        return [$translation->getTitle(), $translation->getMetaTitle()];
    }

    /** Another editor's committed change of a translation field, straight in the database (the entity manager does not see it). */
    private function sql(string $pageKey, string $field, string $value): void
    {
        $metadata = $this->em->getClassMetadata(BuilderPageTranslation::class);
        $page     = $this->em->getRepository(BuilderPage::class)->findOneBy(['pageKey' => $pageKey]);
        self::assertInstanceOf(BuilderPage::class, $page);
        $this->em->getConnection()->executeStatement(
            'UPDATE ' . $metadata->getTableName() . ' SET ' . $metadata->getColumnName($field) . ' = ? WHERE ' . $metadata->getSingleAssociationJoinColumnName('page') . ' = ? AND ' . $metadata->getColumnName('locale') . ' = ?',
            [$value, $page->getId(), 'en'],
        );
    }

    /**
     * The inputs a browser posts from the page: hidden and text inputs, checked checkboxes.
     *
     * @return array<string, string>
     */
    private function formFields(string $html): array
    {
        $fields = [];
        preg_match_all('/<input\b[^>]*>/i', $html, $inputs);
        foreach ($inputs[0] as $input) {
            preg_match_all('/([\w-]+)="([^"]*)"/', $input, $attributes, PREG_SET_ORDER);
            $attr = [];
            foreach ($attributes as $attribute) {
                $attr[strtolower($attribute[1])] = html_entity_decode($attribute[2], ENT_QUOTES | ENT_HTML5);
            }
            $type = $attr['type'] ?? 'text';
            if (!isset($attr['name']) || ($type === 'checkbox' && !str_contains($input, 'checked')) || in_array($type, ['submit', 'button', 'file'], true)) {
                continue;
            }
            $fields[$attr['name']] = $type === 'checkbox' ? ($attr['value'] ?? '1') : ($attr['value'] ?? '');
        }

        return $fields;
    }

    private function inputValue(string $html, string $name): string
    {
        return $this->formFields($html)[$name] ?? '';
    }

    /**
     * The form's fields with $changes applied, as the parameters of a POST (nested names become arrays).
     *
     * @param array<string, string> $fields
     * @param array<string, string> $changes
     *
     * @return array<string, mixed>
     */
    private function postedFields(array $fields, array $changes): array
    {
        $query = '';
        foreach ([...$fields, ...$changes] as $name => $value) {
            $query .= '&' . rawurlencode((string) $name) . '=' . rawurlencode($value);
        }
        parse_str($query, $parameters);
        $posted = [];
        foreach ($parameters as $key => $value) {
            $posted[(string) $key] = $value;
        }

        return $posted;
    }
}
