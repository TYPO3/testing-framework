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

use Psr\Container\ContainerInterface;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Core\ClassLoadingInformation;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\TestingFramework\Core\Functional\Framework\ComposerMode\ComposerModeInstance;
use TYPO3\TestingFramework\Core\TestingBootstrapRunner;

/**
 * Helpers for functional tests that have to work in classic and composer mode test
 * instances alike.
 *
 * @internal Used by TYPO3 Core functional tests, may change any time.
 */
final class TestInstance
{
    /**
     * Boots the current test instance again, the way an entry script would.
     *
     * Tests that exercise an application (the install tool, most notably) bootstrap a
     * second container themselves. In composer mode that bootstrap must read the package
     * artifact of the test instance rather than the one of the root installation.
     */
    public static function boot(bool $failsafe = false): ContainerInterface
    {
        return ComposerModeInstance::isEnabled()
            ? TestingBootstrapRunner::init(ClassLoadingInformation::getClassLoader(), $failsafe)
            : Bootstrap::init(ClassLoadingInformation::getClassLoader(), $failsafe);
    }

    /**
     * Path of the document root relative to the project root, with a trailing slash, or
     * an empty string when the two are the same directory.
     *
     * A "PKG:typo3/app:…" resource identifier carries a path relative to the *project
     * root*, which is the document root only in classic mode - in composer mode the same
     * file lives one level deeper, below public/.
     */
    public static function getAppRelativePublicPath(): string
    {
        if (Environment::getPublicPath() === Environment::getProjectPath()) {
            return '';
        }

        return substr(Environment::getPublicPath(), strlen(Environment::getProjectPath()) + 1) . '/';
    }

    /**
     * Absolute path of the PHP script that runs a console command in a sub process
     * against the given test instance.
     *
     * Classic mode instances use the core binary directly. Composer mode instances can
     * not, see ComposerModeInstance::writeCliEntryPoint().
     *
     * @param non-empty-string $instancePath
     * @return non-empty-string
     */
    public static function getCliEntryPoint(string $instancePath): string
    {
        if (ComposerModeInstance::isEnabled()) {
            return $instancePath . '/' . ComposerModeInstance::CLI_ENTRY_POINT;
        }

        return $instancePath . '/typo3/sysext/core/bin/typo3';
    }
}
