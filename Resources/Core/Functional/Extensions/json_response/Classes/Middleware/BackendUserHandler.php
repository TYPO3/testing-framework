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
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\Context\WorkspaceAspect;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendBackendUserAuthentication;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;

/**
 * Middleware to log in a backend user in a frontend functional test
 * and force this user into a workspace if needed. Similar to
 * FrontendUserHandler middleware.
 */
class BackendUserHandler implements \TYPO3\CMS\Core\SingletonInterface, MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var InternalRequestContext $internalRequestContext */
        $internalRequestContext = $request->getAttribute('typo3.testing.context');
        $backendUserId = $internalRequestContext->getBackendUserId();
        $workspaceId = $internalRequestContext->getWorkspaceId();

        if ((int)$backendUserId === 0) {
            // Skip if $backendUserId is invalid, typically null or 0
            return $handler->handle($request);
        }

        $row = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('be_users')
            ->select(['*'], 'be_users', ['uid' => $backendUserId])
            ->fetchAssociative();
        if ($row !== false) {
            // Init backend user if found in database
            $backendUser = GeneralUtility::makeInstance(FrontendBackendUserAuthentication::class);
            $backendUser->user = $row;
            if ($workspaceId !== null) {
                // Force backend user into given workspace, can be 0, too.
                $backendUser->setTemporaryWorkspace($workspaceId);
            }
            $GLOBALS['BE_USER'] = $backendUser;
            $this->setBackendUserAspect(GeneralUtility::makeInstance(Context::class), $backendUser);
        }
        return $handler->handle($request);
    }

    /**
     * Register the backend user as aspect
     */
    protected function setBackendUserAspect(Context $context, BackendUserAuthentication $user): void
    {
        $context->setAspect('backend.user', GeneralUtility::makeInstance(UserAspect::class, $user));
        $context->setAspect('workspace', GeneralUtility::makeInstance(WorkspaceAspect::class, $user->workspace));
    }
}
