<?php
declare(strict_types=1);
namespace TYPO3\TestingFramework\Core\Acceptance\Step;

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

/**
 * Trait for AcceptanceTester ("$I") extending testing class
 * with helper methods for the backend.
 */
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