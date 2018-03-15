<?php
namespace Helper;

class Acceptance extends \Codeception\Module
{
    /**
     * Check for browser console errors after each step
     * There is also an option to use _after() instead, but it causes Codeception to stop execution
     * and mark test still as success (the failure is caught on PHPUnit level)
     *
     * @param \Codeception\Step $step
     */
    public function _afterStep(\Codeception\Step $step)
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
                $time = date('H:i:s', $logEntry['timestamp'] / 1000) .
                    // Append the milliseconds to the end of the time string
                    '.' . ($logEntry['timestamp'] % 1000);
                $messages[] = "{$time} {$logEntry['level']} - {$logEntry['message']}";
            }
        }
        if (empty($messages)) {
            return;
        }
        $messages = ['Found following JavaScript errors in the browser console:'] + $messages;
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
