<?php
namespace TYPO3\TestingFramework\Core\Functional\Framework\Frontend;

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

use TYPO3\CMS\Core\Utility\ArrayUtility;

/**
 * Bootstrap for direct CLI Request
 */
class RequestBootstrap
{
    /**
     * @var string
     */
    private $documentRoot;

    /**
     * @var array
     */
    private $requestArguments;

    /**
     * @var \Composer\Autoload\ClassLoader
     */
    private $classLoader;

    /**
     * @var InternalRequestContext
     */
    private $context;

    /**
     * @var InternalRequest
     */
    private $request;

    /**
     * @param string $documentRoot
     * @param array|null $this->requestArguments
     */
    public function __construct(string $documentRoot, array $requestArguments = null)
    {
        $this->documentRoot = $documentRoot;
        $this->requestArguments = $requestArguments;
        $this->initialize();
        $this->setGlobalVariables();
    }

    private function initialize()
    {
        $directory = dirname(realpath($this->documentRoot . '/index.php'));
        $this->classLoader = require_once $directory . '/vendor/autoload.php';
    }

    /**
     * @return void
     */
    private function setGlobalVariables()
    {
        if (empty($this->requestArguments)) {
            die('No JSON encoded arguments given');
        }

        if (empty($this->requestArguments['documentRoot'])) {
            die('No documentRoot given');
        }

        if (!empty($this->requestArguments['requestUrl'])) {
            die('Using request URL has been removed, use request object instead');
        }

        if (empty($this->requestArguments['request'])) {
            die('No request object given');
        }

        $this->context = InternalRequestContext::fromArray(json_decode($this->requestArguments['context'], true));
        $this->request = InternalRequest::fromArray(json_decode($this->requestArguments['request'], true));
        $requestUrlParts = parse_url($this->request->getUri());

        // Populating $_GET and $_REQUEST is query part is set:
        if (isset($requestUrlParts['query'])) {
            parse_str($requestUrlParts['query'], $_GET);
            parse_str($requestUrlParts['query'], $_REQUEST);
        }

        // Populating $_POST
        $_POST = [];
        // Populating $_COOKIE
        $_COOKIE = [];

        // Setting up the server environment
        $_SERVER = [];
        $_SERVER['X_TYPO3_TESTING_FRAMEWORK'] = [
            'context' => $this->context,
            'request' => $this->request,
            'withJsonResponse' => $this->requestArguments['withJsonResponse'] ?? true,
        ];
        $_SERVER['DOCUMENT_ROOT'] = $this->requestArguments['documentRoot'];
        $_SERVER['HTTP_USER_AGENT'] = 'TYPO3 Functional Test Request';
        $_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = isset($requestUrlParts['host']) ? $requestUrlParts['host'] : 'localhost';
        $_SERVER['SERVER_ADDR'] = $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = '/index.php';
        $_SERVER['SCRIPT_FILENAME'] = $_SERVER['_'] = $_SERVER['PATH_TRANSLATED'] = $this->requestArguments['documentRoot'] . '/index.php';
        $_SERVER['QUERY_STRING'] = (isset($requestUrlParts['query']) ? $requestUrlParts['query'] : '');
        $_SERVER['REQUEST_URI'] = $requestUrlParts['path'] . (isset($requestUrlParts['query']) ? '?' . $requestUrlParts['query'] : '');
        $_SERVER['REQUEST_METHOD'] = 'GET';

        // Define HTTPS and server port:
        if (isset($requestUrlParts['scheme'])) {
            if ($requestUrlParts['scheme'] === 'https') {
                $_SERVER['HTTPS'] = 'on';
                $_SERVER['SERVER_PORT'] = '443';
            } else {
                $_SERVER['SERVER_PORT'] = '80';
            }
        }

        // Define a port if used in the URL:
        if (isset($requestUrlParts['port'])) {
            $_SERVER['SERVER_PORT'] = $requestUrlParts['port'];
        }

        if (!is_dir($_SERVER['DOCUMENT_ROOT'])) {
            die('Document root directory "' . $_SERVER['DOCUMENT_ROOT'] . '" does not exist');
        }

        if (!is_file($_SERVER['SCRIPT_FILENAME'])) {
            die('Script file "' . $_SERVER['SCRIPT_FILENAME'] . '" does not exist');
        }

        putenv('TYPO3_CONTEXT=Testing/Frontend');
    }

    /**
     * @return void
     */
    public function executeAndOutput()
    {
        global $TT, $TSFE, $TYPO3_CONF_VARS, $BE_USER, $TYPO3_MISC;

        $result = ['status' => 'failure', 'content' => null, 'error' => null];

        ob_start();
        try {
            chdir($_SERVER['DOCUMENT_ROOT']);
            \TYPO3\CMS\Core\Core\SystemEnvironmentBuilder::run(
                0,
                \TYPO3\CMS\Core\Core\SystemEnvironmentBuilder::REQUESTTYPE_FE
            );
            $container = \TYPO3\CMS\Core\Core\Bootstrap::init($this->classLoader);
            ArrayUtility::mergeRecursiveWithOverrule(
                $GLOBALS,
                $this->context->getGlobalSettings() ?? []
            );
            $container->get(\TYPO3\CMS\Frontend\Http\Application::class)->run();
            $result['status'] = 'success';
            $result['content'] = static::getContent();
        } catch (\Exception $exception) {
            $result['error'] = $exception->__toString();
            $result['exception'] = [
                'type' => get_class($exception),
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
            ];
        }
        ob_end_clean();

        echo json_encode($result);
    }

    /**
     * @return null|InternalRequest
     */
    public static function getInternalRequest(): ?InternalRequest
    {
        return $_SERVER['X_TYPO3_TESTING_FRAMEWORK']['request'] ?? null;
    }

    /**
     * @return null|InternalRequestContext
     */
    public static function getInternalRequestContext(): ?InternalRequestContext
    {
        return $_SERVER['X_TYPO3_TESTING_FRAMEWORK']['context'] ?? null;
    }

    /**
     * @return bool
     * @deprecated since TYPO3 v9.4, will be removed in TYPO3 v10.0
     */
    public static function shallUseWithJsonResponse(): bool
    {
        return $_SERVER['X_TYPO3_TESTING_FRAMEWORK']['withJsonResponse'] ?? true;
    }

    /**
     * @return null|string|array
     */
    private static function getContent()
    {
        $content = ob_get_contents();
        if (static::shallUseWithJsonResponse()) {
            $content = json_decode($content, true);
        }
        return $content;
    }
}
