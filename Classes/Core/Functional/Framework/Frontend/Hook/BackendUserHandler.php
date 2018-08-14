<?php
namespace TYPO3\TestingFramework\Core\Functional\Framework\Frontend\Hook;

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

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\RequestBootstrap;

/**
 * Handler for backend user
 */
class BackendUserHandler implements \TYPO3\CMS\Core\SingletonInterface
{
    /**
     * @param array $parameters
     * @param \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController $frontendController
     */
    public function initialize(array $parameters, \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController $frontendController)
    {
        $context = RequestBootstrap::getInternalRequestContext();
        if (empty($context) || empty($context->getBackendUserId()) || empty($context->getWorkspaceId())) {
            return;
        }

        $backendUser = $this->createBackendUser();
        $backendUser->user = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('be_users')
            ->select(['*'], 'be_users', ['uid' => $context->getBackendUserId()])
            ->fetch();
        $backendUser->setTemporaryWorkspace($context->getWorkspaceId());
        // @todo Deprecated, switch to aspect
        $frontendController->beUserLogin = true;

        $parameters['BE_USER'] = $backendUser;
        $GLOBALS['BE_USER'] = $backendUser;
    }

    /**
     * @return \TYPO3\CMS\Backend\FrontendBackendUserAuthentication
     */
    protected function createBackendUser()
    {
        return GeneralUtility::makeInstance(
            \TYPO3\CMS\Backend\FrontendBackendUserAuthentication::class
        );
    }
}
