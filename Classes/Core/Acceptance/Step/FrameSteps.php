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

use Codeception\Exception\MalformedLocatorException;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;

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

    /**
     * Move the module menu of the main frame to the middle of the given element matched by the given locator.
     *
     * ``` php
     * <?php
     * $moduleMenuPaddingTop = 80;
     * $I->scrollModuleMenuTo(['id' => 'file'], 0, -$moduleMenuPaddingTop);
     * ?>
     * ```
     *
     * @param array $toSelector
     * @param int $offsetX
     * @param int $offsetY
     */
    public function scrollModuleMenuTo(array $toSelector, int $offsetX = 0, int $offsetY = 0): void
    {
        $scrollingElement = 'document.getElementsByClassName("scaffold-modulemenu")[0]';
        $this->scrollFrameTo($scrollingElement, $toSelector, $offsetX, $offsetY);
    }

    /**
     * Move the module menu of the main frame to top.
     */
    public function scrollModuleMenuToTop(): void
    {
        $scrollingElement = 'document.getElementsByClassName("scaffold-modulemenu")[0]';
        $this->scrollFrameToTop($scrollingElement);
    }

    /**
     * Move the module menu of the main frame to the bottom.
     */
    public function scrollModuleMenuToBottom(): void
    {
        $scrollingElement = 'document.getElementsByClassName("scaffold-modulemenu")[0]';
        $this->scrollFrameToBottom($scrollingElement);
    }

    /**
     * Move the page tree of the main frame to the middle of the given element matched by the given locator.
     *
     * ``` php
     * <?php
     * $pageTreePadding = 120;
     * $I->scrollPageTreeTo(['css' => '.node.identifier-0_32'], 0, -$pageTreePadding);
     * ?>
     * ```
     *
     * @param array $toSelector
     * @param int $offsetX
     * @param int $offsetY
     */
    public function scrollPageTreeTo(array $toSelector, int $offsetX = 0, int $offsetY = 0): void
    {
        $scrollingElement = 'document.getElementById("typo3-pagetree-tree")';
        $this->scrollFrameTo($scrollingElement, $toSelector, $offsetX, $offsetY);
    }
    /**
     * Move the page tree of the main frame to top.
     */
    public function scrollPageTreeToTop(): void
    {
        $scrollingElement = 'document.getElementById("typo3-pagetree-tree")';
        $this->scrollFrameToTop($scrollingElement);
    }

    /**
     * Move the page tree of the main frame to the bottom.
     */
    public function scrollPageTreeToBottom(): void
    {
        $scrollingElement = 'document.getElementById("typo3-pagetree-tree")';
        $this->scrollFrameToBottom($scrollingElement);
    }

    /**
     * Move the module body of the content frame to the middle of the given element matched by the given locator.
     *
     * ``` php
     * <?php
     * $moduleBodyPadding = 80;
     * $I->scrollModuleBodyTo(['css' => '.t3js-impexp-preview'], 0, -$moduleBodyPadding);
     * ?>
     * ```
     *
     * @param array $toSelector
     * @param int $offsetX
     * @param int $offsetY
     */
    public function scrollModuleBodyTo(array $toSelector, int $offsetX = 0, int $offsetY = 0): void
    {
        $scrollingElement = 'document.getElementsByClassName("module-body")[0]';
        $this->scrollFrameTo($scrollingElement, $toSelector, $offsetX, $offsetY);
    }

    /**
     * Move the module body of the content frame to top.
     */
    public function scrollModuleBodyToTop(): void
    {
        $scrollingElement = 'document.getElementsByClassName("module-body")[0]';
        $this->scrollFrameToTop($scrollingElement);
    }

    /**
     * Move the module body of the content frame to the bottom.
     */
    public function scrollModuleBodyToBottom(): void
    {
        $scrollingElement = 'document.getElementsByClassName("module-body")[0]';
        $this->scrollFrameToBottom($scrollingElement);
    }

    /**
     * Move the TYPO3 backend frame to the middle of the given element matched by the given locator.
     * Extra shift, calculated from the top-left corner of the element,
     * can be set by passing $offsetX and $offsetY parameters.
     *
     * @param string $scrollingElement
     * @param array $toSelector
     * @param int $offsetX
     * @param int $offsetY
     *
     * @see \Codeception\Module\WebDriver::scrollTo
     */
    protected function scrollFrameTo(string $scrollingElement, array $toSelector, int $offsetX = 0, int $offsetY = 0): void
    {
        $I = $this;
        $I->executeInSelenium(
            function (RemoteWebDriver $webDriver) use ($scrollingElement, $toSelector, $offsetX, $offsetY) {
                $el = $webDriver->findElement($this->getStrictLocator($toSelector));
                $x = $el->getLocation()->getX() + $offsetX;
                $y = $el->getLocation()->getY() + $offsetY;
                $webDriver->executeScript("$scrollingElement.scrollTo($x, $y)");
            }
        );
    }

    /**
     * Move the TYPO3 backend frame to top.
     *
     * @param string $scrollingElement
     */
    protected function scrollFrameToTop(string $scrollingElement): void
    {
        $I = $this;
        $I->executeInSelenium(
            function (RemoteWebDriver $webDriver) use ($scrollingElement) {
                $webDriver->executeScript("$scrollingElement.scrollTop = 0");
            }
        );
    }

    /**
     * Move the TYPO3 backend frame to the bottom.
     *
     * @param string $scrollingElement
     */
    protected function scrollFrameToBottom(string $scrollingElement): void
    {
        $I = $this;
        $I->executeInSelenium(
            function (RemoteWebDriver $webDriver) use ($scrollingElement) {
                $webDriver->executeScript("$scrollingElement.scrollTop = $scrollingElement.scrollHeight");
            }
        );
    }

    /**
     * @param array $by
     * @return WebDriverBy
     *
     * @see \Codeception\Module\WebDriver::getStrictLocator
     */
    protected function getStrictLocator(array $by): WebDriverBy
    {
        $type = key($by);
        $locator = $by[$type];
        switch ($type) {
            case 'id':
                return WebDriverBy::id($locator);
            case 'name':
                return WebDriverBy::name($locator);
            case 'css':
                return WebDriverBy::cssSelector($locator);
            case 'xpath':
                return WebDriverBy::xpath($locator);
            case 'link':
                return WebDriverBy::linkText($locator);
            case 'class':
                return WebDriverBy::className($locator);
            default:
                throw new MalformedLocatorException(
                    "$type => $locator",
                    'Strict locator can be either xpath, css, id, link, class, name: '
                );
        }
    }
}
