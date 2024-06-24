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

use TYPO3\CMS\Core\Utility\GeneralUtility;

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
    protected static array $state = [];

    /**
     * Push current state to stack
     */
    public static function push(): void
    {
        $state = [];
        $state['globals-server'] = $GLOBALS['_SERVER'];
        $state['globals-beUser'] = $GLOBALS['BE_USER'] ?? null;
        // Might be possible to drop this ...
        $state['globals-typo3-conf-vars'] = $GLOBALS['TYPO3_CONF_VARS'] ?: null;

        // Backing up TCA *should* not be needed: TCA is (hopefully) never changed after bootstrap and identical in FE and BE.
        // Some tests change TCA on the fly (e.g. core DataHandling/Regular/Modify localizeContentWithEmptyTcaIntegrityColumns).
        // A FE call then resets this TCA change since it initializes the global again. Then, after the FE call, the TCA is
        // different. And code that runs after that within the test scope may fail (eg. the referenceIndex check in tearDown() that
        // relies on TCA). So we back up TCA for now before executing frontend tests.
        $state['globals-tca'] = $GLOBALS['TCA'];
        $state['request'] = $GLOBALS['TYPO3_REQUEST'] ?? null;

        // Can be dropped when GeneralUtility::getIndpEnv() is abandoned
        $generalUtilityReflection = new \ReflectionClass(GeneralUtility::class);
        $generalUtilityIndpEnvCache = $generalUtilityReflection->getProperty('indpEnvCache');
        $state['generalUtilityIndpEnvCache'] = $generalUtilityIndpEnvCache->getValue();

        $state['generalUtilitySingletonInstances'] = GeneralUtility::getSingletonInstances();

        self::$state[] = $state;
    }

    /**
     * Reset some state before functional frontend tests are executed
     */
    public static function reset(): void
    {
        unset($GLOBALS['BE_USER']);
        unset($GLOBALS['TYPO3_REQUEST']);

        $generalUtilityReflection = new \ReflectionClass(GeneralUtility::class);
        $generalUtilityIndpEnvCache = $generalUtilityReflection->getProperty('indpEnvCache');
        $generalUtilityIndpEnvCache->setValue(null, []);

        GeneralUtility::resetSingletonInstances([]);
    }

    /**
     * Pop state from stash and apply again to set state back to 'before frontend call'
     */
    public static function pop(): void
    {
        $state = array_pop(self::$state);

        $GLOBALS['_SERVER'] = $state['globals-server'];
        if ($state['globals-beUser'] !== null) {
            $GLOBALS['BE_USER'] = $state['globals-beUser'];
        }
        if ($state['globals-typo3-conf-vars'] !== null) {
            $GLOBALS['TYPO3_CONF_VARS'] = $state['globals-typo3-conf-vars'];
        }

        $GLOBALS['TCA'] = $state['globals-tca'];
        $GLOBALS['TYPO3_REQUEST'] = $state['request'];

        $generalUtilityReflection = new \ReflectionClass(GeneralUtility::class);
        $generalUtilityIndpEnvCache = $generalUtilityReflection->getProperty('indpEnvCache');
        $generalUtilityIndpEnvCache->setValue(null, $state['generalUtilityIndpEnvCache']);

        GeneralUtility::resetSingletonInstances($state['generalUtilitySingletonInstances']);
    }
}
