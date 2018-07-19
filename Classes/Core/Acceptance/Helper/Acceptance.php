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

use Codeception\Module;
use Codeception\Step;

/**
 * Helper class to verify javascript browser console does not throw errors.
 */
class Acceptance extends Module
{
    /**
     * Wait for backend progress bar to finish / disappear before "click" steps are performed.
     *
     * @param Step $step
     */
    public function _beforeStep(Step $step)
    {
        if ($step->getAction() === 'click') {
            $this->debug('Waiting for nprogress to hide...');
            $this->getModule('WebDriver')->waitForElementNotVisible('#nprogress', 10);
        }
    }

    /**
     * Check for browser console errors after each step
     * There is also an option to use _after() instead, but it causes Codeception to stop execution
     * and mark test still as success (the failure is caught on PHPUnit level)
     *
     * @param \Codeception\Step $step
     */
    public function _afterStep(Step $step)
    {
        $this->assertEmptyBrowserConsole();
    }

    /**
     * Check browser console for errors and fail
     *
     * See Codeception\Module\WebDriver::logJSErrors
     */
    public function assertEmptyBrowserConsole()
    {
        $webDriver = $this->getModule('WebDriver');
        $browserLogEntries = $webDriver->webDriver->manage()->getLog('browser');

        $messages = [];
        foreach ($browserLogEntries as $logEntry) {
            // We fail only on errors. Warnings and info messages are OK.
            if (true === isset($logEntry['level'])
                && true === isset($logEntry['message'])
                && $this->isJSError($logEntry['level'], $logEntry['message'])
            ) {
                // Timestamp is in milliseconds, but date() requires seconds.
                $time = date('H:i:s', (int)($logEntry['timestamp'] / 1000));
                // Append the milliseconds to the end of the time string
                $ms = $logEntry['timestamp'] % 1000;
                $messages[] = "{$time}.{$ms} {$logEntry['level']} - {$logEntry['message']}";
            }
        }
        if (empty($messages)) {
            return;
        }
        $messages = array_merge(['Found following JavaScript errors in the browser console:'], $messages);
        $message = implode(PHP_EOL, $messages);
        $this->fail($message);
    }

    /**
     * COPIED FROM Codeception\Module\WebDriver
     *
     * Determines if the log entry is an error.
     * The decision is made depending on browser and log-level.
     *
     * @param string $logEntryLevel
     * @param string $message
     * @return bool
     */
    protected function isJSError($logEntryLevel, $message)
    {
        return $logEntryLevel === 'SEVERE' && strpos($message, 'ERR_PROXY_CONNECTION_FAILED') === false;
    }
}
