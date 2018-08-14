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
 * Handler for frontend user
 */
class FrontendUserHandler implements \TYPO3\CMS\Core\SingletonInterface
{
    /**
     * Initialize
     *
     * @param array $parameters
     * @param \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController $frontendController
     */
    public function initialize(array $parameters, \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController $frontendController)
    {
        $context = RequestBootstrap::getInternalRequestContext();
        if (empty($context) || empty($context->getFrontendUserId())) {
            return;
        }

        $frontendController->fe_user->checkPid = 0;

        $frontendUser = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('fe_users')
            ->select(['*'], 'fe_users', ['uid' => $context->getFrontendUserId()])
            ->fetch();
        if (is_array($frontendUser)) {
            // @todo Deprecated, switch to aspect
            $frontendController->loginUser = 1;
            $frontendController->fe_user->createUserSession($frontendUser);
            $frontendController->fe_user->user = $GLOBALS['TSFE']->fe_user->fetchUserSession();
            $frontendController->initUserGroups();
        }
    }
}
