<?php

declare(strict_types=1);

namespace TYPO3\TestingFramework\Core\Jwt;

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Session\UserSession;

/**
 * @internal This class is only for cross core compat. This will not be available in the next major version.
 */
class SessionHelper
{
    public function createSessionCookieValue(string $userSessionId): string
    {
        return $this->getParentSessionHelper()->createSessionCookieValue($userSessionId);
    }

    public function resolveSessionCookieValue(UserSession $session): string
    {
        return $this->getParentSessionHelper()->resolveSessionCookieValue($session);
    }

    /**
     * @return SessionHelperEleven|SessionHelperTwelve
     */
    protected function getParentSessionHelper()
    {
        return ((new Typo3Version())->getMajorVersion() >= 12)
            ? new SessionHelperTwelve()
            : new SessionHelperEleven();
    }
}
