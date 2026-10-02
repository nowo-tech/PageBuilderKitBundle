<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Service;

use Nowo\PageBuilderKitBundle\Enum\ContentFieldType;

use function array_key_exists;
use function count;
use function explode;
use function htmlspecialchars;
use function is_array;
use function is_bool;
use function is_numeric;
use function is_scalar;
use function is_string;
use function preg_replace_callback;
use function sprintf;
use function str_contains;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

/**
 * Replaces Twig-free content-field slots: [[fields.path]] or [[@fields.path]].
 */
final readonly class ContentFieldSlotReplacer
{
    private const string PATTERN = '/\[\[\s*@?fields\.([a-zA-Z0-9_.]+)\s*\]\]/';

    /**
     * @param array<string, mixed> $fields resolved bag for the locale
     * @param list<array<string, mixed>> $schema
     */
    public function replace(string $html, array $fields, array $schema = []): string
    {
        if ($html === '' || !str_contains($html, '[[')) {
            return $html;
        }

        $schemaIndex = [];
        foreach ($schema as $field) {
            if (is_string($field['key'] ?? null)) {
                $schemaIndex[$field['key']] = $field;
            }
        }

        return (string) preg_replace_callback(
            self::PATTERN,
            function (array $matches) use ($fields, $schemaIndex): string {
                $path  = explode('.', $matches[1]);
                $value = $this->resolvePath($fields, $path);
                $raw   = $this->pathAllowsRaw($schemaIndex, $path);

                return $this->stringify($value, $raw);
            },
            $html,
        );
    }

    /**
     * @param array<string, mixed> $bag
     * @param list<string> $path
     */
    private function resolvePath(array $bag, array $path): mixed
    {
        $current = $bag;
        foreach ($path as $segment) {
            if (is_array($current)) {
                if (array_key_exists($segment, $current)) {
                    $current = $current[$segment];
                    continue;
                }
                if (is_numeric($segment) && array_key_exists((int) $segment, $current)) {
                    $current = $current[(int) $segment];
                    continue;
                }
            }

            return null;
        }

        return $current;
    }

    /**
     * @param array<string, array<string, mixed>> $schemaIndex
     * @param list<string> $path
     */
    private function pathAllowsRaw(array $schemaIndex, array $path): bool
    {
        if ($path === []) {
            return false;
        }

        $field = $schemaIndex[$path[0]] ?? null;
        if ($field === null) {
            return false;
        }

        if (count($path) === 1) {
            return $this->typeIsRaw((string) ($field['type'] ?? ''));
        }

        $cursor = $field;
        for ($i = 1, $len = count($path); $i < $len; ++$i) {
            $segment = $path[$i];
            $type    = ContentFieldType::tryFrom((string) ($cursor['type'] ?? ''));
            if ($type === ContentFieldType::Repeater && is_numeric($segment)) {
                continue;
            }
            if ($type === ContentFieldType::Repeater || $type === ContentFieldType::Group) {
                $found = null;
                foreach ($cursor['fields'] ?? [] as $sub) {
                    if (($sub['key'] ?? null) === $segment) {
                        $found = $sub;
                        break;
                    }
                }
                if ($found === null) {
                    return false;
                }
                $cursor = $found;
                continue;
            }

            return $this->typeIsRaw((string) ($cursor['type'] ?? ''));
        }

        return $this->typeIsRaw((string) ($cursor['type'] ?? ''));
    }

    private function typeIsRaw(string $type): bool
    {
        $enum = ContentFieldType::tryFrom($type);

        return $enum instanceof ContentFieldType && $enum->isHtmlOutput();
    }

    private function stringify(mixed $value, bool $raw): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_array($value)) {
            return $raw ? '' : htmlspecialchars(sprintf('[%d items]', count($value)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        if (!is_scalar($value)) {
            return '';
        }

        $text = (string) $value;

        return $raw ? $text : htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
