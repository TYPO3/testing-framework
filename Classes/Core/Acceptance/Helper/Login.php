<?php

namespace TYPO3\TestingFramework\Core\Acceptance\Helper;

use Codeception\Exception\ConfigurationException;

class Login extends \Codeception\Module
{
    protected $config = [
        'sessions' => []
    ];

    public function useExistingSession($role = '')
    {
        $wd = $this->getModule('WebDriver');
        $wd->amOnPage('/typo3/index.php');

        $sessionCookie = '';
        if ($role) {
            if (!isset($this->config['sessions'][$role])) {
                throw new ConfigurationException("Helper\Login doesn't have `sessions` defined for $role");
            }
            $sessionCookie = $this->config['sessions'][$role];
        }

        // @todo: There is a bug in PhantomJS / firefox (?) where adding a cookie fails.
        // This bug will be fixed in the next PhantomJS version but i also found
        // this workaround. First reset / delete the cookie and than set it and catch
        // the webdriver exception as the cookie has been set successful.
        try {
            $wd->resetCookie('be_typo_user');
            $wd->setCookie('be_typo_user', $sessionCookie);
        } catch (\Facebook\WebDriver\Exception\UnableToSetCookieException $e) {
        }
        try {
            $wd->resetCookie('be_lastLoginProvider');
            $wd->setCookie('be_lastLoginProvider', '1433416747');
        } catch (\Facebook\WebDriver\Exception\UnableToSetCookieException $e) {
        }

        // reload the page to have a logged in backend
        $wd->amOnPage('/typo3/index.php');

        // Ensure main content frame is fully loaded, otherwise there are load-race-conditions
        $wd->switchToIFrame('list_frame');
        $wd->waitForText('Web Content Management System');
        // And switch back to main frame preparing a click to main module for the following main test case
        $wd->switchToIFrame();
    }
}