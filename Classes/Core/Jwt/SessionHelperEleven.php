<?php

declare(strict_types=1);

namespace TYPO3\TestingFramework\Core\Jwt;

use TYPO3\CMS\Core\Session\UserSession;

/**
 * @internal This class is only for cross core compat. This will not be available in the next major version.
 */
class SessionHelperEleven
{
    public function createSessionCookieValue(string $userSessionId): string
    {
        return $userSessionId;
    }

    public function resolveSessionCookieValue(UserSession $session): string
    {
        return $session->getIdentifier();
    }
}
