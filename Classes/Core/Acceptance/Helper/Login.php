<?php

declare(strict_types=1);
namespace TYPO3\TestingFramework\Core\Acceptance\Helper;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Codeception\Exception\ConfigurationException;
use Codeception\Module;
use Codeception\Module\WebDriver;
use Codeception\Util\Locator;

/**
 * Helper class to log in backend users and load backend.
 */
class Login extends Module
{
    /**
     * @var array Filled by .yml config with valid sessions per role
     */
    protected $config = [
        'sessions' => [],
    ];

    /**
     * Set a backend user session cookie and load the backend index.php.
     *
     * Use this action to change the backend user and avoid switching between users in the backend module
     * "Backend Users" as this will change the user session ID and make it useless for subsequent calls of this action.
     *
     * @param string $role The backend user who should be logged in.
     * @throws ConfigurationException
     */
    public function useExistingSession($role = '')
    {
        $webDriver = $this->getWebDriver();

        $newUserSessionId = $this->getUserSessionIdByRole($role);

        $hasSession = $this->_loadSession();
        if ($hasSession && $newUserSessionId !== '' && $newUserSessionId !== $this->getUserSessionId()) {
            $this->_deleteSession();
            $hasSession = false;
        }

        if (!$hasSession) {
            $webDriver->amOnPage('/typo3/index.php');
            $webDriver->waitForElement('body[data-typo3-login-ready]');
            $this->_createSession($newUserSessionId);
        }

        // Reload the page to have a logged in backend.
        $webDriver->amOnPage('/typo3/index.php');

        // Ensure main content frame is fully loaded, otherwise there are load-race-conditions ..
        $webDriver->waitForElement('iframe[name="list_frame"]');
        $webDriver->switchToIFrame('list_frame');
        $webDriver->waitForElement(Locator::firstElement('div.module'));
        // .. and switch back to main frame.
        $webDriver->switchToIFrame();
    }

    /**
     * @param string $role
     * @return string
     * @throws ConfigurationException
     */
    protected function getUserSessionIdByRole($role)
    {
        if (empty($role)) {
            return '';
        }

        if (!isset($this->_getConfig('sessions')[$role])) {
            throw new ConfigurationException(sprintf(
                'Backend user session ID cannot be resolved for role "%s": ' .
                'Set session ID explicitly in configuration of module Login.',
                $role
            ), 1627554106);
        }

        return $this->_getConfig('sessions')[$role];
    }

    /**
     * @return bool
     */
    public function _loadSession()
    {
        return $this->getWebDriver()->loadSessionSnapshot('login');
    }

    public function _deleteSession()
    {
        $webDriver = $this->getWebDriver();
        $webDriver->resetCookie('be_typo_user');
        $webDriver->resetCookie('be_lastLoginProvider');
        $webDriver->deleteSessionSnapshot('login');
    }

    /**
     * @param string $userSessionId
     */
    public function _createSession($userSessionId)
    {
        $webDriver = $this->getWebDriver();
        $webDriver->setCookie('be_typo_user', $userSessionId);
        $webDriver->setCookie('be_lastLoginProvider', '1433416747');
        $webDriver->saveSessionSnapshot('login');
    }

    /**
     * @return string
     */
    protected function getUserSessionId()
    {
        $userSessionId = $this->getWebDriver()->grabCookie('be_typo_user');
        return $userSessionId ?? '';
    }

    /**
     * @return WebDriver
     * @throws \Codeception\Exception\ModuleException
     */
    protected function getWebDriver()
    {
        return $this->getModule('WebDriver');
    }
}
