<?php

namespace TYPO3\JsonResponse\Framework\Frontend;

use TYPO3\CMS\Core\Http\ImmediateResponseException;

/**
 * Class Application
 *
 * Extends the main application because we need our own ServerRequestFactory
 * to get the body content.
 *
 * @package TYPO3\TestingFramework\Core\Functional\Framework\Frontend
 */
class Application extends \TYPO3\CMS\Frontend\Http\Application
{
    /**
     * Set up the application and shut it down afterwards
     *
     * @param callable $execute
     */
    final public function runFromTestingFramework(callable $execute = null)
    {
        try {
            $response = $this->handle(
                ServerRequestFactory::fromGlobals()
            );
            if ($execute !== null) {
                call_user_func($execute);
            }
        } catch (ImmediateResponseException $exception) {
            $response = $exception->getResponse();
        }

        $this->sendResponse($response);
    }

}