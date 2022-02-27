<?php

declare(strict_types=1);
namespace TYPO3\TestingFramework\Core\Functional\Framework;

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

use TYPO3\CMS\Core\Database\ReferenceIndex;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;

/**
 * This is an ugly helper class to manage some TYPO3 framework state.
 *
 * It is used in functional tests that fire a frontend request (->executeFrontendSubRequest()).
 *
 * Since TYPO3 still has a couple of static properties and globals that are 'tainted' after a
 * request has been handled, this class is a helper to get rid of this state again.
 *
 * This class should not be needed. It is a manifest of technical core debt.
 * It should shrink over time and vanish altogether in the end.
 */
class FrameworkState
{
    protected static $state = [];

    /**
     * Push current state to stack
     */
    public static function push()
    {
        $state = [];
        $state['globals-server'] = $GLOBALS['_SERVER'];
        $state['globals-get'] = $_GET;
        $state['globals-post'] = $_POST;
        $state['globals-request'] = $_REQUEST;
        $state['globals-beUser'] = $GLOBALS['BE_USER'] ?? null;
        // Might be possible to drop this ...
        $state['globals-typo3-conf-vars'] = $GLOBALS['TYPO3_CONF_VARS'] ?: null;

        // Backing up TCA *should* not be needed: TCA is (hopefully) never changed after bootstrap and identical in FE and BE.
        // Some tests change TCA on the fly (eg. core DataHandling/Regular/Modify localizeContentWithEmptyTcaIntegrityColumns).
        // A FE call then resets this TCA change since it initializes the global again. Then, after the FE call, the TCA is
        // different. And code that runs after that within the test scope may fail (eg. the referenceIndex check in tearDown() that
        // relies on TCA). So we backup TCA for now before executing frontend tests.
        $state['globals-tca'] = $GLOBALS['TCA'];

        // Can be dropped when GeneralUtility::getIndpEnv() is abandoned
        $generalUtilityReflection = new \ReflectionClass(GeneralUtility::class);
        $generalUtilityIndpEnvCache = $generalUtilityReflection->getProperty('indpEnvCache');
        $generalUtilityIndpEnvCache->setAccessible(true);
        $state['generalUtilityIndpEnvCache'] = $generalUtilityIndpEnvCache->getValue();

        $state['generalUtilitySingletonInstances'] = GeneralUtility::getSingletonInstances();

        // Infamous RootlineUtility carries various static state ...
        $rootlineUtilityReflection = new \ReflectionClass(RootlineUtility::class);
        $rootlineUtilityLocalCache = $rootlineUtilityReflection->getProperty('localCache');
        $rootlineUtilityLocalCache->setAccessible(true);
        $state['rootlineUtilityLocalCache'] = $rootlineUtilityLocalCache->getValue();
        $rootlineUtilityRootlineFields = $rootlineUtilityReflection->getProperty('rootlineFields');
        $rootlineUtilityRootlineFields->setAccessible(true);
        $state['rootlineUtilityRootlineFields'] = $rootlineUtilityRootlineFields->getValue();
        $rootlineUtilityPageRecordCache = $rootlineUtilityReflection->getProperty('pageRecordCache');
        $rootlineUtilityPageRecordCache->setAccessible(true);
        $state['rootlineUtilityPageRecordCache'] = $rootlineUtilityPageRecordCache->getValue();

        self::$state[] = $state;
    }

    /**
     * Reset some state before functional frontend tests are executed
     */
    public static function reset()
    {
        unset($GLOBALS['BE_USER']);

        $generalUtilityReflection = new \ReflectionClass(GeneralUtility::class);
        $generalUtilityIndpEnvCache = $generalUtilityReflection->getProperty('indpEnvCache');
        $generalUtilityIndpEnvCache->setAccessible(true);
        $generalUtilityIndpEnvCache->setValue([]);

        GeneralUtility::resetSingletonInstances([]);

        RootlineUtility::purgeCaches();
        $rootlineUtilityReflection = new \ReflectionClass(RootlineUtility::class);
        $rootlineFieldsDefault = $rootlineUtilityReflection->getDefaultProperties();
        $rootlineFieldsDefault = $rootlineFieldsDefault['rootlineFields'];
        $rootlineUtilityRootlineFields = $rootlineUtilityReflection->getProperty('rootlineFields');
        $rootlineUtilityRootlineFields->setAccessible(true);
        $state['rootlineUtilityRootlineFields'] = $rootlineFieldsDefault;
    }

    /**
     * Pop state from stash and apply again to set state back to 'before frontend call'
     */
    public static function pop()
    {
        $state = array_pop(self::$state);

        $GLOBALS['_SERVER'] = $state['globals-server'];
        $_GET = $state['globals-get'];
        $_POST = $state['globals-post'];
        $_REQUEST = $state['globals-request'];

        if ($state['globals-beUser'] !== null) {
            $GLOBALS['BE_USER'] = $state['globals-beUser'];
        }
        if ($state['globals-typo3-conf-vars'] !== null) {
            $GLOBALS['TYPO3_CONF_VARS'] = $state['globals-typo3-conf-vars'];
        }

        $GLOBALS['TCA'] = $state['globals-tca'];

        $generalUtilityReflection = new \ReflectionClass(GeneralUtility::class);
        $generalUtilityIndpEnvCache = $generalUtilityReflection->getProperty('indpEnvCache');
        $generalUtilityIndpEnvCache->setAccessible(true);
        $generalUtilityIndpEnvCache->setValue($state['generalUtilityIndpEnvCache']);

        GeneralUtility::resetSingletonInstances($state['generalUtilitySingletonInstances']);

        $rootlineUtilityReflection = new \ReflectionClass(RootlineUtility::class);
        $rootlineUtilityLocalCache = $rootlineUtilityReflection->getProperty('localCache');
        $rootlineUtilityLocalCache->setAccessible(true);
        $rootlineUtilityLocalCache->setValue($state['rootlineUtilityLocalCache']);
        $rootlineUtilityRootlineFields = $rootlineUtilityReflection->getProperty('rootlineFields');
        $rootlineUtilityRootlineFields->setAccessible(true);
        $rootlineUtilityRootlineFields->setValue($state['rootlineUtilityRootlineFields']);
        $rootlineUtilityPageRecordCache = $rootlineUtilityReflection->getProperty('pageRecordCache');
        $rootlineUtilityPageRecordCache->setAccessible(true);
        $rootlineUtilityPageRecordCache->setValue($state['rootlineUtilityPageRecordCache']);
    }
}
