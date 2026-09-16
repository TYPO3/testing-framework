<?php

declare(strict_types=1);

namespace TYPO3\TestingFramework\Core\Functional\Framework\ComposerMode;

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

use TYPO3\CMS\Core\Package\Cache\PackageCacheEntry;
use TYPO3\CMS\Core\Package\Cache\PackageCacheInterface;

/**
 * Hands a prepared package cache entry to the bootstrap.
 *
 * In composer mode TYPO3 reads its package artifact from a fixed location inside the
 * vendor directory, which is shared by every test instance. The active package set has
 * to differ per test case, so the entry is passed in memory instead of being written to
 * disk - writing it would mean test cases racing over one file.
 *
 * @internal
 */
final class InMemoryPackageCache implements PackageCacheInterface
{
    public function __construct(private readonly PackageCacheEntry $entry) {}

    public function fetch(): PackageCacheEntry
    {
        return $this->entry;
    }

    public function store(PackageCacheEntry $cacheEntry): void
    {
        // Nothing to store: the entry is derived from the superset artifact on every boot.
    }

    public function invalidate(): void
    {
        // Nothing to invalidate for the same reason.
    }

    public function getIdentifier(): string
    {
        return $this->entry->getIdentifier() ?? 'testing-framework-composer-mode';
    }
}
