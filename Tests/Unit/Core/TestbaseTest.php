<?php

declare(strict_types=1);

namespace TYPO3\TestingFramework\Tests\Unit\Core;

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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\TestingFramework\Core\Testbase;

final class TestbaseTest extends TestCase
{
    private string $instancePath = '';

    protected function tearDown(): void
    {
        if ($this->instancePath !== '' && is_dir($this->instancePath)) {
            $this->removeDirectory($this->instancePath);
        }
        $this->instancePath = '';
        parent::tearDown();
    }

    /**
     * Testbase::linkTestExtensionsToInstance() symlinks a test extension using the
     * extension key determined by ComposerPackageManager::getPackageInfoWithFallback().
     * Testbase::setUpPackageStates() must determine the very same name, otherwise the
     * written PackageStates.php points to a non-existing package path.
     */
    #[Test]
    public function setUpPackageStatesUsesExtensionKeyOfTestExtensionAndNotItsFolderName(): void
    {
        $extensionPath = __DIR__ . '/Fixtures/Extensions/ext_folder_name';
        // Folder name and extension key of the fixture extension differ on purpose.
        self::assertSame('ext_folder_name', basename($extensionPath));

        $this->instancePath = $this->createTestInstance();
        // Symlink the extension the way linkTestExtensionsToInstance() does it.
        symlink($extensionPath, $this->instancePath . '/typo3conf/ext/diverging_extension_key');

        $subject = new Testbase();
        $subject->setUpPackageStates($this->instancePath, [], [], [$extensionPath], []);

        $packageStates = require $this->instancePath . '/typo3conf/PackageStates.php';

        self::assertArrayHasKey('diverging_extension_key', $packageStates['packages']);
        self::assertArrayNotHasKey('ext_folder_name', $packageStates['packages']);
        self::assertSame(
            'typo3conf/ext/diverging_extension_key/',
            $packageStates['packages']['diverging_extension_key']['packagePath']
        );
    }

    private function createTestInstance(): string
    {
        $instancePath = sys_get_temp_dir() . '/testbase-' . bin2hex(random_bytes(8));
        mkdir($instancePath . '/typo3conf/ext', 0775, true);
        return $instancePath;
    }

    private function removeDirectory(string $path): void
    {
        foreach ((array)scandir($path) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $entryPath = $path . '/' . $entry;
            if (is_link($entryPath) || is_file($entryPath)) {
                unlink($entryPath);
                continue;
            }
            $this->removeDirectory($entryPath);
        }
        rmdir($path);
    }
}
