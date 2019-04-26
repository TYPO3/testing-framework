<?php


namespace TYPO3\TestingFramework\Core\Functional\Framework\Frontend;


use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class ServerRequestFactory
 *
 * Extends the default ServerRequestFactory because we need the body content
 * and the headers from the testing framework request which is passed along
 * in the _SERVER variables.
 *
 * @package TYPO3\TestingFramework\Core\Functional\Framework\Frontend
 */
class ServerRequestFactory extends \TYPO3\CMS\Core\Http\ServerRequestFactory
{
    /**
     * Create a request from the original superglobal variables.
     *
     * @return ServerRequest
     * @throws \InvalidArgumentException when invalid file values given
     * @internal Note that this is not public API yet.
     */
    public static function fromGlobals()
    {
        $serverParameters = $_SERVER;
        $headers = static::prepareHeaders($serverParameters);

        $method = $serverParameters['REQUEST_METHOD'] ?? 'GET';
        $uri = new Uri(GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL'));

        $request = new ServerRequest(
            $uri,
            $method,
            $serverParameters['X_TYPO3_TESTING_FRAMEWORK']['request']->getBody(),
            $serverParameters['X_TYPO3_TESTING_FRAMEWORK']['request']->getHeaders(),
            $serverParameters,
            static::normalizeUploadedFiles($_FILES)
        );

        if (!empty($_COOKIE)) {
            $request = $request->withCookieParams($_COOKIE);
        }
        $queryParameters = GeneralUtility::_GET();
        if (!empty($queryParameters)) {
            $request = $request->withQueryParams($queryParameters);
        }
        $parsedBody = GeneralUtility::_POST();
        if (empty($parsedBody) && in_array($method, ['PUT', 'PATCH', 'DELETE'])) {
            parse_str((string)$_SERVER['X_TYPO3_TESTING_FRAMEWORK']['request']->getBody(), $parsedBody);
        }
        if (!empty($parsedBody)) {
            $request = $request->withParsedBody($parsedBody);
        }
        return $request;
    }

}