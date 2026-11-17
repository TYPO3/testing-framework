<?php

declare(strict_types=1);

namespace TYPO3\TestingFramework\Tests\Unit\JsonResponse\Middleware;

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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\JsonResponse\Middleware\BackendUserHandler;
use TYPO3\JsonResponse\Middleware\FrontendUserHandler;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class UserHandlerWithoutTestingContextTest extends UnitTestCase
{
    public static function userHandlerDataProvider(): \Generator
    {
        yield 'frontend user handler' => [
            'middleware' => new FrontendUserHandler(),
        ];
        yield 'backend user handler' => [
            'middleware' => new BackendUserHandler(),
        ];
    }

    /**
     * A frontend request may reach the middleware stack without passing
     * FunctionalTestCase::executeFrontendSubRequest(), e.g. a sub-request
     * an extension builds and dispatches itself, so it carries no
     * typo3.testing.context attribute.
     */
    #[Test]
    #[DataProvider('userHandlerDataProvider')]
    public function requestWithoutTestingContextIsPassedOn(MiddlewareInterface $middleware): void
    {
        $request = new ServerRequest('https://website.local/', 'GET');
        $expectedResponse = new Response();
        $handler = new class ($expectedResponse) implements RequestHandlerInterface {
            public ?ServerRequestInterface $handledRequest = null;

            public function __construct(private readonly ResponseInterface $response) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->handledRequest = $request;
                return $this->response;
            }
        };

        $response = $middleware->process($request, $handler);

        self::assertSame($expectedResponse, $response);
        self::assertSame($request, $handler->handledRequest);
    }
}
