<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Media;

use finfo;
use LogicException;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;
use WeakMap;

use function class_exists;
use function clearstatcache;
use function filesize;
use function finfo_file;
use function finfo_open;
use function is_file;
use function is_string;

use const FILEINFO_MIME_TYPE;

/**
 * Holds the values of each {@see AssetUpload} outside the object (and, for an HTTP upload, the HttpFoundation file it was
 * built from), so no dump of the VO can reach the temporary path or the client name; builds the VO of an HTTP upload.
 *
 * Keyed by the VO in a {@see WeakMap}: an entry disappears with its VO, so nothing outlives the request in a FrankenPHP
 * worker (no reset needed, REQ-RUNTIME-002).
 *
 * @internal
 */
final class AssetUploadData
{
    /** @var WeakMap<AssetUpload, array{path: string, clientName: string, size: int|null, mimeType: string|null}>|null */
    private static ?WeakMap $values = null; // @phpstan-ignore frankenphp.worker.noMutableStaticProperty (WeakMap keyed by the per-request VO)

    /** @var WeakMap<AssetUpload, UploadedFile>|null */
    private static ?WeakMap $origins = null; // @phpstan-ignore frankenphp.worker.noMutableStaticProperty (WeakMap keyed by the per-request VO)

    /**
     * @param array{path: string, clientName: string, size: int|null, mimeType: string|null} $values
     */
    public static function put(AssetUpload $upload, array $values): void
    {
        // @igor-ignore - WeakMap keyed by the per-request VO; entries are collected with it.
        self::$values ??= new WeakMap(); // @phpstan-ignore frankenphp.worker.noMutableStaticProperty
        // @igor-ignore - WeakMap keyed by the per-request VO; entries are collected with it.
        self::$values[$upload] = $values; // @phpstan-ignore frankenphp.worker.noMutableStaticProperty
    }

    /**
     * The VO of an HTTP upload: client name, real size, and the MIME type the built-in storages resolved before 1.15
     * (finfo on the temporary file, else the client type, else the MIME guesser).
     */
    public static function fromUploadedFile(UploadedFile $file): AssetUpload
    {
        $size   = $file->getSize();
        $upload = new AssetUpload(
            $file->getPathname(),
            $file->getClientOriginalName(),
            $size === false ? null : $size,
            self::resolveUploadedMimeType($file),
        );

        // @igor-ignore - WeakMap keyed by the per-request VO; entries are collected with it.
        self::$origins ??= new WeakMap(); // @phpstan-ignore frankenphp.worker.noMutableStaticProperty
        // @igor-ignore - WeakMap keyed by the per-request VO; entries are collected with it.
        self::$origins[$upload] = $file; // @phpstan-ignore frankenphp.worker.noMutableStaticProperty

        return $upload;
    }

    /**
     * @return array{path: string, clientName: string, size: int|null, mimeType: string|null}
     */
    public static function get(AssetUpload $upload): array
    {
        if (!isset(self::$values[$upload])) {
            // @codeCoverageIgnoreStart
            throw new LogicException('This asset upload has no data.');
            // @codeCoverageIgnoreEnd
        }

        return self::$values[$upload];
    }

    /** The local path of the upload (for the built-in storages). */
    public static function pathOf(AssetUpload $upload): string
    {
        return self::get($upload)['path'];
    }

    public static function move(AssetUpload $upload, string $directory, ?string $name): string
    {
        $values = self::get($upload);
        $source = self::$origins[$upload] ?? new File($values['path'], false);
        $name   = $name === null ? null : AssetUpload::lastSegment($name);

        try {
            $moved = $source->move($directory, $name);
        } catch (FileException $exception) {
            throw new RuntimeException($exception->getMessage(), 0, $exception);
        }

        $values['path'] = $moved->getPathname();
        self::put($upload, $values);
        unset(self::$origins[$upload]);

        return $moved->getPathname();
    }

    /**
     * Size of the file on disk (MZ-B-1): the built-in storages never trust the size a caller put in the VO. Falls back
     * to the VO's size only when the file cannot be stat'ed.
     */
    public static function serverSize(AssetUpload $upload): ?int
    {
        $path = self::pathOf($upload);
        clearstatcache(true, $path);
        $size = is_file($path) ? @filesize($path) : false;

        return $size === false ? $upload->getSize() : $size;
    }

    /**
     * MIME type detected by finfo on the file on disk (MZ-B-1): the built-in storages never trust the type a caller put
     * in the VO. Only when finfo has no answer, the VO's type (for an HTTP upload: the same client-type / guesser
     * fallback as before 1.15).
     */
    public static function serverMimeType(AssetUpload $upload): string
    {
        return self::detectMimeType(self::pathOf($upload)) ?? $upload->getMimeType() ?? '';
    }

    /** finfo on the content; null when finfo is missing or has no answer. */
    public static function detectMimeType(string $path): ?string
    {
        if ($path !== '' && class_exists(finfo::class)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detected = @finfo_file($finfo, $path);
                if (is_string($detected) && $detected !== '') {
                    return $detected;
                }
            }
        }

        return null;
    }

    /**
     * The resolution the built-in storages used on an `UploadedFile` before 1.15 (identical result).
     */
    private static function resolveUploadedMimeType(UploadedFile $file): string
    {
        $detected = self::detectMimeType($file->getPathname());
        if ($detected !== null) {
            return $detected;
        }

        $client = $file->getClientMimeType();
        if ($client !== '') {
            return $client;
        }

        // @codeCoverageIgnoreStart
        try {
            return $file->getMimeType() ?: '';
        } catch (Throwable) {
            return '';
        }
        // @codeCoverageIgnoreEnd
    }
}
