<?php

declare(strict_types=1);

namespace TYPO3\TestingFramework\Core;

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

use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Package\Cache\ComposerPackageArtifact;
use TYPO3\CMS\Core\Package\Cache\PackageCacheInterface;
use TYPO3\TestingFramework\Core\Functional\Framework\ComposerMode\ComposerModeInstance;

/**
 * Bootstrap of composer mode test instances.
 *
 * In composer mode TYPO3 reads its package artifact from the vendor directory, which
 * every test instance shares by symlink. Test cases need different active package sets,
 * so each instance boots from its own artifact instead, see
 * ComposerModeInstance::writePackageArtifact().
 *
 * Bootstrap::init() resolves createPackageCache() through late static binding, so
 * overriding that single method is enough. Nothing else is overridden.
 *
 * Only ever loaded in composer mode, which needs TYPO3 v14.3 or later: Bootstrap is a
 * readonly class since v14.3, and a readonly class can only extend a readonly class.
 *
 * @internal
 */
readonly class TestingBootstrapRunner extends Bootstrap
{
    public static function createPackageCache(FrontendInterface $coreCache): PackageCacheInterface
    {
        if (!Environment::isComposerMode()) {
            return parent::createPackageCache($coreCache);
        }

        return new ComposerPackageArtifact(ComposerModeInstance::getPackageArtifactDirectory(Environment::getProjectPath()));
    }
}
