<?php

namespace TYPO3\JsonResponse\Middleware;

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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Session\UserSessionManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;

/**
 * Create a frontend user session if a frontend functional test wants
 * to run something with a logged in user - InternalRequestContext->withFrontendUserId().
 * Adds the created cookie value to the Request object.
 *
 * This middleware is executed *before* the core frontend user
 * authentication middleware, which will then find the cookie plus the
 * valid session and logs in the user.
 */
class FrontendUserHandler implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var InternalRequestContext $internalRequestContext */
        $internalRequestContext = $request->getAttribute('typo3.testing.context');
        $frontendUserId = $internalRequestContext->getFrontendUserId();

        if ($frontendUserId === null) {
            // Skip if test does not use a logged in user
            return $handler->handle($request);
        }

        $frontendUser = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('fe_users')
            ->select(['*'], 'fe_users', ['uid' => $frontendUserId])
            ->fetchAssociative();
        if (is_array($frontendUser)) {
            $userSessionManager = UserSessionManager::create('FE');
            $userSession = $userSessionManager->createAnonymousSession();
            $userSessionManager->elevateToFixatedUserSession($userSession, $frontendUserId);
            $request = $request->withCookieParams(
                array_replace(
                    $request->getCookieParams(),
                    [
                        'fe_typo_user' => $userSession->getJwt(),
                    ]
                )
            );
        }
        return $handler->handle($request);
    }
}
