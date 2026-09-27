<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Service;

use function array_key_exists;
use function array_keys;
use function array_unique;
use function array_values;
use function count;
use function explode;
use function implode;
use function is_array;
use function is_string;
use function json_encode;
use function max;
use function min;
use function sort;
use function sprintf;
use function str_replace;
use function strlen;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Lightweight structural diff between two page documents (live vs revision / import).
 */
final class DocumentDiff
{
    public const int VISUAL_MAX_LINES = 400;

    /**
     * @param array<string, mixed> $leftStructure
     * @param array<string, mixed> $rightStructure
     * @param array<string, mixed> $leftProps
     * @param array<string, mixed> $rightProps
     *
     * @return array{
     *     identical: bool,
     *     leftEngine: string,
     *     rightEngine: string,
     *     structureChanged: bool,
     *     propsChanged: bool,
     *     summary: list<string>,
     *     changedPaths: list<string>,
     *     panels: array{
     *         html?: array{left: string, right: string, rows: list<array{left: string, right: string, type: string}>, truncated: bool},
     *         css?: array{left: string, right: string, rows: list<array{left: string, right: string, type: string}>, truncated: bool},
     *         structure?: array{left: string, right: string, rows: list<array{left: string, right: string, type: string}>, truncated: bool}
     *     }
     * }
     */
    public function compare(
        array $leftStructure,
        array $rightStructure,
        array $leftProps = [],
        array $rightProps = [],
    ): array {
        $leftEngine  = $this->engineOf($leftStructure);
        $rightEngine = $this->engineOf($rightStructure);

        $structureChanged = $this->encode($leftStructure) !== $this->encode($rightStructure);
        $propsChanged     = $this->encode($leftProps) !== $this->encode($rightProps);
        $changedPaths     = $this->pathDiff($leftStructure, $rightStructure);
        $changedPaths     = array_values(array_unique([...$changedPaths, ...$this->pathDiff($leftProps, $rightProps, 'props')]));

        $summary = [];
        if ($leftEngine !== $rightEngine) {
            $summary[] = sprintf('Engine: %s → %s', $leftEngine, $rightEngine);
        }
        if ($structureChanged) {
            $summary[] = sprintf('Structure differs (%d path(s))', count($changedPaths) > 0 ? count($changedPaths) : 1);
            $summary   = [...$summary, ...$this->sizeHints($leftStructure, $rightStructure)];
        }
        if ($propsChanged) {
            $summary[] = sprintf(
                'Widget props locales: %s → %s',
                implode(',', array_keys($leftProps)),
                implode(',', array_keys($rightProps)),
            );
        }
        if ($summary === []) {
            $summary[] = 'No differences';
        }

        return [
            'identical'        => !$structureChanged && !$propsChanged,
            'leftEngine'       => $leftEngine,
            'rightEngine'      => $rightEngine,
            'structureChanged' => $structureChanged,
            'propsChanged'     => $propsChanged,
            'summary'          => $summary,
            'changedPaths'     => $changedPaths,
            'panels'           => $this->visualPanels($leftStructure, $rightStructure),
        ];
    }

    /**
     * Side-by-side line panels for admin revision diff (HTML/CSS or classic JSON).
     *
     * @param array<string, mixed> $leftStructure
     * @param array<string, mixed> $rightStructure
     *
     * @return array{
     *     html?: array{left: string, right: string, rows: list<array{left: string, right: string, type: string}>, truncated: bool},
     *     css?: array{left: string, right: string, rows: list<array{left: string, right: string, type: string}>, truncated: bool},
     *     structure?: array{left: string, right: string, rows: list<array{left: string, right: string, type: string}>, truncated: bool}
     * }
     */
    public function visualPanels(array $leftStructure, array $rightStructure): array
    {
        $leftEngine  = $this->engineOf($leftStructure);
        $rightEngine = $this->engineOf($rightStructure);
        $panels      = [];

        if ($leftEngine === DocumentNormalizer::ENGINE_GRAPESJS
            || $rightEngine === DocumentNormalizer::ENGINE_GRAPESJS) {
            $panels['html'] = $this->sideBySide(
                $this->stringField($leftStructure, 'html'),
                $this->stringField($rightStructure, 'html'),
            );
            $panels['css'] = $this->sideBySide(
                $this->stringField($leftStructure, 'css'),
                $this->stringField($rightStructure, 'css'),
            );
        }

        if ($leftEngine === 'classic' || $rightEngine === 'classic'
            || !isset($panels['html'])) {
            $panels['structure'] = $this->sideBySide(
                $this->prettyJson($leftStructure),
                $this->prettyJson($rightStructure),
            );
        }

        return $panels;
    }

    /**
     * @return array{left: string, right: string, rows: list<array{left: string, right: string, type: string}>, truncated: bool}
     */
    public function sideBySide(string $left, string $right, int $maxLines = self::VISUAL_MAX_LINES): array
    {
        $leftLines  = $this->splitLines($left);
        $rightLines = $this->splitLines($right);
        $total      = max(count($leftLines), count($rightLines));
        $limit      = min($total, $maxLines);
        $rows       = [];

        for ($i = 0; $i < $limit; ++$i) {
            $hasL = array_key_exists($i, $leftLines);
            $hasR = array_key_exists($i, $rightLines);
            $lv   = $hasL ? $leftLines[$i] : '';
            $rv   = $hasR ? $rightLines[$i] : '';

            $type = 'same';
            if (!$hasL && $hasR) {
                $type = 'added';
            } elseif ($hasL && !$hasR) {
                $type = 'removed';
            } elseif ($lv !== $rv) {
                $type = 'changed';
            }

            $rows[] = ['left' => $lv, 'right' => $rv, 'type' => $type];
        }

        return [
            'left'      => $left,
            'right'     => $right,
            'rows'      => $rows,
            'truncated' => $total > $maxLines,
        ];
    }

    /**
     * @param array<string, mixed> $structure
     */
    private function engineOf(array $structure): string
    {
        return ($structure['engine'] ?? null) === DocumentNormalizer::ENGINE_GRAPESJS
            ? DocumentNormalizer::ENGINE_GRAPESJS
            : 'classic';
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     *
     * @return list<string>
     */
    private function pathDiff(array $left, array $right, string $prefix = ''): array
    {
        $paths = [];
        $keys  = array_unique([...array_keys($left), ...array_keys($right)]);
        sort($keys);

        foreach ($keys as $key) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            $hasL = array_key_exists($key, $left);
            $hasR = array_key_exists($key, $right);

            if (!$hasL || !$hasR) {
                $paths[] = $path;
                continue;
            }

            $l = $left[$key];
            $r = $right[$key];

            if (is_array($l) && is_array($r)) {
                /** @var array<string, mixed> $l */
                /** @var array<string, mixed> $r */
                $nested = $this->pathDiff($l, $r, $path);
                if ($nested !== []) {
                    $paths = [...$paths, ...$nested];
                }
                continue;
            }

            if ($this->encode($l) !== $this->encode($r)) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     *
     * @return list<string>
     */
    private function sizeHints(array $left, array $right): array
    {
        $hints = [];
        if (($left['engine'] ?? null) === DocumentNormalizer::ENGINE_GRAPESJS
            || ($right['engine'] ?? null) === DocumentNormalizer::ENGINE_GRAPESJS) {
            $leftHtml  = $this->htmlLength($left);
            $rightHtml = $this->htmlLength($right);
            if ($leftHtml !== $rightHtml) {
                $hints[] = sprintf('HTML length: %d → %d', $leftHtml, $rightHtml);
            }
        } else {
            $leftSections  = is_array($left['sections'] ?? null) ? count($left['sections']) : 0;
            $rightSections = is_array($right['sections'] ?? null) ? count($right['sections']) : 0;
            if ($leftSections !== $rightSections) {
                $hints[] = sprintf('Sections: %d → %d', $leftSections, $rightSections);
            }
        }

        return $hints;
    }

    /**
     * @param array<string, mixed> $structure
     */
    private function htmlLength(array $structure): int
    {
        $html = $structure['html'] ?? '';
        if (!is_string($html)) {
            $html = '';
        }

        return strlen($html);
    }

    private function encode(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $structure
     */
    private function stringField(array $structure, string $key): string
    {
        $value = $structure[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    /**
     * @param array<string, mixed> $structure
     */
    private function prettyJson(array $structure): string
    {
        return json_encode(
            $structure,
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * @return list<string>
     */
    private function splitLines(string $text): array
    {
        if ($text === '') {
            return [];
        }

        return explode("\n", str_replace(["\r\n", "\r"], "\n", $text));
    }
}
