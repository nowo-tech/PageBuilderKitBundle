<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Service;

use function array_key_exists;
use function array_keys;
use function array_unique;
use function array_values;
use function count;
use function implode;
use function is_array;
use function is_string;
use function json_encode;
use function sort;
use function sprintf;
use function strlen;

use const JSON_THROW_ON_ERROR;

/**
 * Lightweight structural diff between two page documents (live vs revision / import).
 */
final class DocumentDiff
{
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
     *     changedPaths: list<string>
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
}
