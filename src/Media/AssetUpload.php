<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Media;

use LogicException;
use RuntimeException;

use function fopen;
use function is_file;
use function is_readable;
use function pathinfo;
use function sprintf;
use function str_replace;
use function strrpos;
use function substr;

use const PATHINFO_EXTENSION;

/**
 * A file uploaded through the GrapesJS Asset Manager, as a bundle value object (1.15, REQ-ARCH-001 rule 3): read it as
 * a stream, move it, and read its client name, size and MIME type, without the HttpFoundation `UploadedFile`.
 *
 * - {@see getMimeType()} is the type **detected on the server** from the content (finfo), with the same fallbacks the
 *   built-in storages always used for an HTTP upload (the client type, then the MIME guesser, when finfo has no
 *   answer). It is what the `allowed_mime` allowlist checks.
 * - The built-in storages do not trust {@see getSize()} / {@see getMimeType()} of a VO they receive: they read the size
 *   and run finfo on the file itself.
 * - {@see getClientName()} is the name the client sent (for an HTTP upload: `UploadedFile::getClientOriginalName()`).
 *   It is untrusted: never use it as a path; {@see moveTo()} keeps only its last segment.
 *
 * None of the values is stored in a property of this object: they live in an internal {@see \WeakMap} keyed by it, so a
 * dump (VarDumper / `dump()`), `var_export()`, `print_r()`, `(array)` cast or `json_encode()` shows no temporary path
 * and no client name. It cannot be cloned or serialized. The entry dies with the object, so nothing outlives the
 * request in a FrankenPHP worker (REQ-RUNTIME-002).
 */
final readonly class AssetUpload
{
    /**
     * @param string $path readable local path of the file (for example the PHP upload temporary file)
     * @param string $clientName the name the client sent (untrusted)
     * @param int|null $size size in bytes, null when unknown
     * @param string|null $mimeType MIME type detected on the server; null = detect it now from the content (finfo)
     */
    public function __construct(string $path, string $clientName, ?int $size = null, ?string $mimeType = null)
    {
        AssetUploadData::put($this, [
            'path'       => $path,
            'clientName' => $clientName,
            'size'       => $size,
            'mimeType'   => $mimeType ?? AssetUploadData::detectMimeType($path),
        ]);
    }

    /** The name the client sent (untrusted: never use it as a path). */
    public function getClientName(): string
    {
        return $this->data()['clientName'];
    }

    /** The extension of the client name (untrusted, like the name), without the dot; '' when there is none. */
    public function getClientExtension(): string
    {
        return pathinfo($this->getClientName(), PATHINFO_EXTENSION);
    }

    /** Size in bytes, null when unknown. */
    public function getSize(): ?int
    {
        return $this->data()['size'];
    }

    /** MIME type detected on the server, null when unknown. */
    public function getMimeType(): ?string
    {
        return $this->data()['mimeType'];
    }

    /**
     * Opens the file for reading. The caller closes the stream.
     *
     * @throws RuntimeException when the file is gone or unreadable (for example after {@see moveTo()})
     *
     * @return resource
     */
    public function openStream()
    {
        $path = $this->data()['path'];

        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('The uploaded asset is not readable (moved or removed).');
        }

        $stream = @fopen($path, 'r');

        // @codeCoverageIgnoreStart
        if ($stream === false) {
            throw new RuntimeException('The uploaded asset cannot be opened.');
        }
        // @codeCoverageIgnoreEnd

        return $stream;
    }

    /**
     * Moves the file into `$directory` (created when missing) and returns the new path. For an HTTP upload this is the
     * same move as `UploadedFile::move()` (`move_uploaded_file()`, same checks and permissions).
     *
     * @param string|null $name target file name; only its last path segment is used. Null keeps the temporary name.
     *
     * @throws RuntimeException when the file cannot be moved
     */
    public function moveTo(string $directory, ?string $name = null): string
    {
        return AssetUploadData::move($this, $directory, $name);
    }

    /** Last path segment of a name (`/` and `\` both separate), as `UploadedFile::move()` does. */
    public static function lastSegment(string $name): string
    {
        $name = str_replace('\\', '/', $name);
        $pos  = strrpos($name, '/');

        return $pos === false ? $name : substr($name, $pos + 1);
    }

    /** @return array<never, never> */
    public function __serialize(): array
    {
        throw new LogicException(sprintf('%s cannot be serialized: it carries upload data.', self::class));
    }

    /** @param array<array-key, mixed> $data */
    public function __unserialize(array $data): void
    {
        throw new LogicException(sprintf('%s cannot be unserialized: it carries upload data.', self::class));
    }

    /**
     * Not cloneable: a copy would carry no data (`clone` fails before this body runs).
     *
     * @codeCoverageIgnore
     */
    private function __clone()
    {
    }

    /**
     * @return array{path: string, clientName: string, size: int|null, mimeType: string|null}
     */
    private function data(): array
    {
        return AssetUploadData::get($this);
    }
}
