<?php

declare(strict_types=1);

namespace TYPO3\TestingFramework\Core\Jwt;

use TYPO3\CMS\Core\Security\JwtTrait;
use TYPO3\CMS\Core\Session\UserSession;

/**
 * @internal This class is only for cross core compat. This will not be available in the next major version.
 */
class SessionHelperTwelve
{
    use JwtTrait;

    public function createSessionCookieValue(string $userSessionId): string
    {
        return self::encodeHashSignedJwt(
            [
                'identifier' => $userSessionId,
                'time' => (new \DateTimeImmutable())->format(\DateTimeImmutable::RFC3339),
            ],
            // relies on $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']
            self::createSigningKeyFromEncryptionKey(UserSession::class)
        );
    }

    public function resolveSessionCookieValue(UserSession $session): string
    {
        return $session->getJwt();
    }
}
