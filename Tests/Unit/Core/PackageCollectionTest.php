<?php

declare(strict_types=1);

/*
 * Copyright (C) 2024 Daniel Siepmann <coding@daniel-siepmann.de>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
 * 02110-1301, USA.
 */

namespace Typo3\TestingFramework\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Service\DependencyOrderingService;
use TYPO3\TestingFramework\Composer\ComposerPackageManager;
use TYPO3\TestingFramework\Core\PackageCollection;

final class PackageCollectionTest extends TestCase
{
    /**
     * @test
     */
    public function sortsComposerPackages(): void
    {
        $packageStates = require __DIR__ . '/../Fixtures/Packages/PackageStates.php';
        $expectedPackageStates = require __DIR__ . '/../Fixtures/Packages/PackageStates_sorted.php';
        $packageStates = $packageStates['packages'];
        $basePath = realpath(__DIR__ . '/../../../');

        $composerPackageManager = new ComposerPackageManager();
        // That way it knows about the extensions, this is done by TestBase upfront.
        $composerPackageManager->getPackageInfoWithFallback(__DIR__ . '/../Fixtures/Packages/package0');
        $composerPackageManager->getPackageInfoWithFallback(__DIR__ . '/../Fixtures/Packages/package1');
        $composerPackageManager->getPackageInfoWithFallback(__DIR__ . '/../Fixtures/Packages/package2');
        $composerPackageManager->getPackageInfoWithFallback(__DIR__ . '/../Fixtures/Packages/package-with-extemconf');

        $subject = PackageCollection::fromPackageStates(
            $composerPackageManager,
            new PackageManager(
                new DependencyOrderingService(),
                __DIR__ . '/../Fixtures/Packages/PackageStates.php',
                $basePath
            ),
            $basePath,
            $packageStates
        );

        $result = $subject->sortPackageStates(
            $packageStates,
            new DependencyOrderingService()
        );

        self::assertSame(5, array_search('package0', array_keys($result)), 'Package 0 is not stored at loading order 5.');
        self::assertSame(6, array_search('package1', array_keys($result)), 'Package 1 is not stored at loading order 6.');
        self::assertSame(7, array_search('extension_with_extemconf', array_keys($result)), 'extension_with_extemconf is not stored at loading order 7.');
        self::assertSame(8, array_search('package2', array_keys($result)), 'Package 2 is not stored at loading order 8.');
        self::assertSame($expectedPackageStates['packages'], $result, 'Sorted packages does not match expected order');
    }
}
