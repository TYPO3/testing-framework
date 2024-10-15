<?php

declare(strict_types=1);

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

namespace TYPO3\TestingFramework\Core;

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder as CoreSystemEnvironmentBuilder;

/**
 * Class that replaces the core's SystemEnvironmentBuilder.
 *
 * It is used to adapt the default bootstrap to the requirements of the
 * Testing Framework, basically enforcing the created TYPO3 instances to not
 * be in Composer mode, so that the PackageManager reads PackageStates.php even in TYPO3 v11
 *
 * The TYPO3 testing instances that are set up by the testing framework are created by linking
 * folders and entry scripts. This makes these instances non Composer instances by definition.
 * However, since at the same time the autoload.php from the root installation is pulled in
 * and this sets a PHP constant, we need to make sure to override this environment property
 * for each created testing instance. This is done by overriding the Core SystemEnvironmentBuilder
 * with a custom one, which sets Composer Mode to false.
 *
 * All that can be removed, once the testing framework creates testing instances using Composer
 *
 * @internal
 */
class SystemEnvironmentBuilder extends CoreSystemEnvironmentBuilder
{
    private static ?bool $composerMode = null;

    /**
     * @todo: Change default $requestType to 0 when dropping support for TYPO3 v12
     */
    public static function run(int $entryPointLevel = 0, int $requestType = CoreSystemEnvironmentBuilder::REQUESTTYPE_FE, ?bool $composerMode = null): void
    {
        self::$composerMode = $composerMode;
        parent::run($entryPointLevel, $requestType);
        Environment::initialize(
            Environment::getContext(),
            Environment::isCli(),
            static::usesComposerClassLoading(),
            Environment::getProjectPath(),
            Environment::getPublicPath(),
            Environment::getVarPath(),
            Environment::getConfigPath(),
            Environment::getCurrentScript(),
            Environment::isWindows() ? 'WINDOWS' : 'UNIX'
        );
    }

    /**
     * Manage composer mode separated from TYPO3_COMPOSER_MODE define set by typo3/cms-composer-installers.
     *
     * Note that this will not with earlier TYPO3 versions than 13.4.
     * @link https://review.typo3.org/c/Packages/TYPO3.CMS/+/86569
     * @link https://github.com/TYPO3/testing-framework/issues/577
     */
    protected static function usesComposerClassLoading(): bool
    {
        return self::$composerMode ?? parent::usesComposerClassLoading();
    }
}
