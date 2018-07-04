<?php


/**
 * Inherited Methods
 * @method void wantToTest($text)
 * @method void wantTo($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method \Codeception\Lib\Friend haveFriend($name, $actorClass = null)
 *
 * @SuppressWarnings(PHPMD)
*/
class AcceptanceTester extends \Codeception\Actor
{
    use _generated\AcceptanceTesterActions;

    /**
     * The session cookie that is used if the session is injected.
     * This session must exist in the database fixture to get a logged in state.
     *
     * @var string
     */
    protected $sessionCookie = '';

    /**
     * Use the existing database session from the fixture by setting the backend user cookie
     */
    public function useExistingSession()
    {
        $I = $this;
        $I->amOnPage('/typo3/index.php');

        // @todo: There is a bug in PhantomJS / firefox (?) where adding a cookie fails.
        // This bug will be fixed in the next PhantomJS version but i also found
        // this workaround. First reset / delete the cookie and than set it and catch
        // the webdriver exception as the cookie has been set successful.
        try {
            $I->resetCookie('be_typo_user');
            $I->setCookie('be_typo_user', $this->sessionCookie);
        } catch (\Facebook\WebDriver\Exception\UnableToSetCookieException $e) {
        }
        try {
            $I->resetCookie('be_lastLoginProvider');
            $I->setCookie('be_lastLoginProvider', '1433416747');
        } catch (\Facebook\WebDriver\Exception\UnableToSetCookieException $e) {
        }

        // reload the page to have a logged in backend
        $I->amOnPage('/typo3/index.php');
        
        // Ensure main content frame is fully loaded, otherwise there are load-race-conditions
        $I->switchToContentFrame();
        $I->waitForText('Web Content Management System');
        // And switch back to main frame preparing a click to main module for the following main test case
        $I->switchToMainFrame();
    }

    /**
     * Helper method switching to main content frame, the one with main module and top bar
     */
    public function switchToMainFrame(): void
    {
        $I = $this;
        $I->waitForElementNotVisible('#nprogress', 120);
        $I->switchToIFrame();
    }

    /**
     * Helper method switching to main content frame, the one with main module and top bar
     */
    public function switchToContentFrame(): void
    {
        $I = $this;
        $I->waitForElementNotVisible('#nprogress', 120);
        $I->switchToIFrame('list_frame');
        $I->waitForElementNotVisible('#nprogress', 120);
    }
}
