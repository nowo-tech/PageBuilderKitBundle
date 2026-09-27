<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Media;

/**
 * Result of storing an uploaded page-builder asset.
 */
final readonly class AssetUploadResult
{
    public function __construct(
        public string $src,
        public string $name,
        public string $type = 'image',
        public ?int $width = null,
        public ?int $height = null,
        public ?string $mimeType = null,
        public ?string $storageKey = null,
    ) {
    }

    /**
     * @return array{src: string, type: string, name: string, width?: int, height?: int}
     */
    public function toGrapesAsset(): array
    {
        $asset = [
            'src'  => $this->src,
            'type' => $this->type,
            'name' => $this->name,
        ];

        if ($this->width !== null) {
            $asset['width'] = $this->width;
        }
        if ($this->height !== null) {
            $asset['height'] = $this->height;
        }

        return $asset;
    }
}
