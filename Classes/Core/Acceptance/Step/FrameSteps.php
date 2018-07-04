<?php
namespace TYPO3\TestingFramework\Core\Acceptance\Step;

trait FrameSteps
{
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