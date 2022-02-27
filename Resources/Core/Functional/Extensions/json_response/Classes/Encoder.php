<?php

namespace TYPO3\JsonResponse;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\ImmediateResponseException;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\RequestBootstrap;

/**
 * @deprecated This middleware will vanish in v12 compatible testing-framework
 */
class Encoder implements MiddlewareInterface
{
    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Fallback for legacy tests that do not expect JSON response
        if (!RequestBootstrap::shallUseWithJsonResponse()) {
            return $handler->handle($request);
        }

        try {
            $response = $handler->handle($request);
        } catch (ImmediateResponseException $exception) {
            $response = $exception->getResponse();
        }

        return new JsonResponse([
            'statusCode' => $response->getStatusCode(),
            'reasonPhrase' => $response->getReasonPhrase(),
            'headers' => $response->getHeaders(),
            'body' => $response->getBody()->__toString(),
        ]);
    }
}
