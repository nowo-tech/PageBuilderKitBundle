<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Resources;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

use function array_filter;
use function count;
use function dirname;
use function implode;
use function in_array;
use function is_array;
use function preg_match;
use function preg_replace;
use function preg_split;
use function sprintf;
use function trim;

use const PREG_SPLIT_NO_EMPTY;

/**
 * Regression: the de, fr, it, nl and pt catalogues shipped about 25 English admin messages each (page sections,
 * SEO form, CSRF error, "Save SEO"). A message may equal the English text only when it is language-neutral:
 * at most two words once %placeholders% are removed (product names, "Editor", "Live"), or listed below.
 */
final class CatalogueNotEnglishCopyTest extends TestCase
{
    private const string DOMAIN = 'NowoPageBuilderKitBundle';

    /** Multi-word English values that stay identical on purpose. */
    private const array ALLOWED_KEYS = [
        'admin.seo.section_social',
    ];

    /** @return iterable<string, array{string}> */
    public static function localeProvider(): iterable
    {
        foreach (['es', 'de', 'fr', 'it', 'nl', 'pt'] as $locale) {
            yield $locale => [$locale];
        }
    }

    #[Test]
    #[DataProvider('localeProvider')]
    public function messagesAreNotEnglishCopies(string $locale): void
    {
        $english = self::flatten(self::load('en'));
        $copies  = [];
        foreach (self::flatten(self::load($locale)) as $key => $value) {
            if (($english[$key] ?? null) !== $value || in_array($key, self::ALLOWED_KEYS, true)) {
                continue;
            }
            if (!self::isLanguageNeutral($value)) {
                $copies[] = sprintf('%s: "%s"', $key, $value);
            }
        }

        self::assertSame([], $copies, sprintf("Untranslated (English) messages in %s:\n%s", $locale, implode("\n", $copies)));
    }

    private static function isLanguageNeutral(string $value): bool
    {
        $words = preg_split('/\s+/', trim((string) preg_replace('/%\w+%/', '', $value)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_filter($words, static fn (string $word): bool => preg_match('/\pL{2,}/u', $word) === 1);

        return count($words) <= 2;
    }

    /** @return array<mixed> */
    private static function load(string $locale): array
    {
        $data = Yaml::parseFile(dirname(__DIR__, 3) . '/src/Resources/translations/' . self::DOMAIN . '.' . $locale . '.yaml');
        self::assertIsArray($data);

        return $data;
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<string, string>
     */
    private static function flatten(array $data, string $prefix = ''): array
    {
        $flat = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $flat += self::flatten($value, $prefix . $key . '.');
            } else {
                $flat[$prefix . $key] = (string) $value;
            }
        }

        return $flat;
    }
}
