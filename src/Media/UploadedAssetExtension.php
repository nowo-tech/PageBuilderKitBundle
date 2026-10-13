<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Media;

use InvalidArgumentException;

use function in_array;
use function preg_replace;
use function sprintf;
use function strtolower;
use function trim;

/**
 * Derives the stored file extension from the detected MIME type (never from the client file name).
 *
 * A polyglot such as a GIF header followed by PHP code named "x.php" passes the image/gif MIME check;
 * keeping the client extension would write an executable "<hex>.php" under the public web root.
 *
 * @internal
 */
final class UploadedAssetExtension
{
    /**
     * Detected MIME type => accepted client extensions (first entry is the stored extension).
     *
     * @var array<string, list<string>>
     */
    private const EXTENSIONS = [
        'image/jpeg'    => ['jpg', 'jpeg', 'jpe'],
        'image/png'     => ['png'],
        'image/gif'     => ['gif'],
        'image/webp'    => ['webp'],
        'image/avif'    => ['avif'],
        'image/svg+xml' => ['svg'],
    ];

    /**
     * Returns the extension to store the upload with.
     *
     * Known MIME types map to their canonical extension; any other (custom allowed) MIME type is stored as "bin".
     *
     * @throws InvalidArgumentException when the client extension does not match the detected MIME type
     */
    public static function resolve(string $mime, string $clientExtension): string
    {
        $accepted = self::EXTENSIONS[$mime] ?? null;
        if ($accepted === null) {
            return 'bin';
        }

        $client = preg_replace('/[^a-z0-9]+/', '', strtolower(trim($clientExtension))) ?? '';
        if (trim($clientExtension) !== '' && !in_array($client, $accepted, true)) {
            throw new InvalidArgumentException(sprintf('File extension "%s" does not match detected MIME type "%s".', $clientExtension, $mime));
        }

        return $accepted[0];
    }
}
