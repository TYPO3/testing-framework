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

use TYPO3\CMS\Core\Package\Cache\PackageCacheInterface;

/**
 * Holds the package cache the next bootstrap should use.
 *
 * Separate from TestingBootstrapRunner because Bootstrap is a readonly class, and a
 * readonly class may not declare a static property with a default value.
 *
 * @internal
 */
final class TestingBootstrapPackageCache
{
    public static ?PackageCacheInterface $packageCache = null;
}
