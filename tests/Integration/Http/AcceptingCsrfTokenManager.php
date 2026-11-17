<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Integration\Http;

use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Test double: every CSRF token is valid, except the value `rejected` (the HTTP tests post without a session).
 */
final class AcceptingCsrfTokenManager implements CsrfTokenManagerInterface
{
    public const string ACCEPTED = 'accepted';
    public const string REJECTED = 'rejected';

    public function getToken(string $tokenId): CsrfToken
    {
        return new CsrfToken($tokenId, self::ACCEPTED);
    }

    public function refreshToken(string $tokenId): CsrfToken
    {
        return new CsrfToken($tokenId, self::ACCEPTED);
    }

    public function removeToken(string $tokenId): ?string
    {
        return null;
    }

    public function isTokenValid(CsrfToken $token): bool
    {
        return $token->getValue() !== self::REJECTED;
    }
}
