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
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\RequestBootstrap;

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
        $context = RequestBootstrap::getInternalRequestContext();
        if (empty($context) || empty($context->getFrontendUserId())) {
            return $handler->handle($request);
        }

        $frontendUserAuthentication = $request->getAttribute('frontend.user');
        $frontendUserAuthentication->checkPid = 0;

        $frontendUser = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('fe_users')
            ->select(['*'], 'fe_users', ['uid' => $context->getFrontendUserId()])
            ->fetch();
        if (is_array($frontendUser)) {
            $frontendUserAuthentication->createUserSession($frontendUser);
            $frontendUserAuthentication->user = $frontendUserAuthentication->fetchUserSession();
            $this->setFrontendUserAspect(GeneralUtility::makeInstance(Context::class), $frontendUserAuthentication);
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
