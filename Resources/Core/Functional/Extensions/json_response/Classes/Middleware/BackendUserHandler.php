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
use TYPO3\CMS\Backend\FrontendBackendUserAuthentication;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\Context\WorkspaceAspect;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\RequestBootstrap;

/**
 * Handler for backend user
 */
class BackendUserHandler implements \TYPO3\CMS\Core\SingletonInterface, MiddlewareInterface
{
    /**
     * @return FrontendBackendUserAuthentication
     */
    protected function createBackendUser()
    {
        return GeneralUtility::makeInstance(FrontendBackendUserAuthentication::class);
    }

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
        if (empty($context) || empty($context->getBackendUserId())) {
            return $handler->handle($request);
        }

        $statement = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('be_users')
            ->select(['*'], 'be_users', ['uid' => $context->getBackendUserId()]);
        if ((new Typo3Version())->getMajorVersion() >= 11) {
            $row = $statement->fetchAssociative();
        } else {
            // @deprecated: Will be removed with next major version - core v10 compat.
            $row = $statement->fetch();
        }
        if ($row !== false) {
            $backendUser = $this->createBackendUser();
            $backendUser->user = $row;
            if (!empty($context->getWorkspaceId())) {
                $backendUser->setTemporaryWorkspace($context->getWorkspaceId());
            }
            $GLOBALS['BE_USER'] = $backendUser;
            $this->setBackendUserAspect(GeneralUtility::makeInstance(Context::class), $backendUser);
        }
        return $handler->handle($request);
    }

    /**
     * Register the backend user as aspect
     *
     * @param Context $context
     * @param BackendUserAuthentication|null $user
     */
    protected function setBackendUserAspect(Context $context, BackendUserAuthentication $user)
    {
        $context->setAspect('backend.user', GeneralUtility::makeInstance(UserAspect::class, $user));
        $context->setAspect('workspace', GeneralUtility::makeInstance(WorkspaceAspect::class, $user->workspace));
    }
}
