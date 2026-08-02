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
use TYPO3\CMS\Core\Package\Cache\PackageCacheInterface;

/**
 * Bootstrap that lets the testing framework decide where package information comes from.
 *
 * In composer mode TYPO3 resolves its package artifact from the vendor directory, which
 * every test instance shares by symlink. Test cases need different active package sets,
 * so the artifact cannot simply be read from that fixed location.
 *
 * Bootstrap::init() resolves createPackageCache() through late static binding, so
 * overriding that single method is enough. Nothing else is overridden.
 *
 * @internal
 */
readonly class TestingBootstrapRunner extends Bootstrap
{
    public static function createPackageCache(FrontendInterface $coreCache): PackageCacheInterface
    {
        return TestingBootstrapPackageCache::$packageCache ?? parent::createPackageCache($coreCache);
    }
}
