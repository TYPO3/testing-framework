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
use TYPO3\CMS\Core\Authentication\AbstractUserAuthentication;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Session\UserSessionManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\RequestBootstrap;
use TYPO3\TestingFramework\Core\Jwt\SessionHelper;

/**
 * Handler for frontend user
 */
class FrontendUserHandler implements MiddlewareInterface
{
    /**
     * Process an incoming server request and return a response, optionally delegating
     * response creation to a handler.
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ((new Typo3Version())->getMajorVersion() >= 12) {
            $context = RequestBootstrap::getInternalRequestContext();
            $frontendUserId = $context->getFrontendUserId();

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
                $sessionHelper = new SessionHelper();
                $request = $request->withCookieParams(
                    array_replace(
                        $request->getCookieParams(),
                        [
                            'fe_typo_user' => $sessionHelper->resolveSessionCookieValue($userSession),
                        ]
                    )
                );
            }
        } else {
            $context = RequestBootstrap::getInternalRequestContext();
            if (empty($context) || empty($context->getFrontendUserId())) {
                return $handler->handle($request);
            }

            /** @var FrontendUserAuthentication $frontendUserAuthentication */
            $frontendUserAuthentication = $request->getAttribute('frontend.user');
            $frontendUserAuthentication->checkPid = 0;

            $statement = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getConnectionForTable('fe_users')
                ->select(['*'], 'fe_users', ['uid' => $context->getFrontendUserId()]);
            $frontendUser = $statement->fetchAssociative();
            if (is_array($frontendUser)) {
                $context = GeneralUtility::makeInstance(Context::class);
                $frontendUserAuthentication->createUserSession($frontendUser);
                $frontendUserAuthentication->user = $frontendUserAuthentication->fetchUserSession();
                $frontendUserAuthentication->fetchGroupData($request);
                $userAspect = $frontendUserAuthentication->createUserAspect();
                GeneralUtility::makeInstance(Context::class)->setAspect('frontend.user', $userAspect);
            }
        }
        return $handler->handle($request);
    }

    /**
     * Register the frontend user as aspect
     *
     * @param Context $context
     * @param AbstractUserAuthentication $user
     */
    protected function setFrontendUserAspect(Context $context, AbstractUserAuthentication $user)
    {
        $context->setAspect('frontend.user', GeneralUtility::makeInstance(UserAspect::class, $user));
    }
}
