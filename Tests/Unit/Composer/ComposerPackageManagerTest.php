<?php

declare(strict_types=1);

namespace TYPO3\TestingFramework\Tests\Unit\Composer;

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

use TYPO3\TestingFramework\Composer\ComposerPackageManager;
use TYPO3\TestingFramework\Composer\PackageInfo;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ComposerPackageManagerTest extends UnitTestCase
{
    public static function sanitizePathReturnsExpectedValueDataProvider(): \Generator
    {
        // relative paths (forward slash)
        yield ['css/./style.css', 'css/style.css'];
        yield ['css/../style.css', 'style.css'];
        yield ['css/./../style.css', 'style.css'];
        yield ['css/.././style.css', 'style.css'];
        yield ['css/../../style.css', '../style.css'];
        yield ['./css/style.css', 'css/style.css'];
        yield ['../css/style.css', '../css/style.css'];
        yield ['./../css/style.css', '../css/style.css'];
        yield ['.././css/style.css', '../css/style.css'];
        yield ['../../css/style.css', '../../css/style.css'];
        yield ['', ''];
        yield ['.', ''];
        yield ['..', '..'];
        yield ['./..', '..'];
        yield ['../.', '..'];
        yield ['../..', '../..'];

        // relative paths (backslash)
        yield ['css\\.\\style.css', 'css/style.css'];
        yield ['css\\..\\style.css', 'style.css'];
        yield ['css\\.\\..\\style.css', 'style.css'];
        yield ['css\\..\\.\\style.css', 'style.css'];
        yield ['css\\..\\..\\style.css', '../style.css'];
        yield ['.\\css\\style.css', 'css/style.css'];
        yield ['..\\css\\style.css', '../css/style.css'];
        yield ['.\\..\\css\\style.css', '../css/style.css'];
        yield ['..\\.\\css\\style.css', '../css/style.css'];
        yield ['..\\..\\css\\style.css', '../../css/style.css'];

        // absolute paths (forward slash, UNIX)
        yield ['/css/style.css', '/css/style.css'];
        yield ['/css/./style.css', '/css/style.css'];
        yield ['/css/../style.css', '/style.css'];
        yield ['/css/./../style.css', '/style.css'];
        yield ['/css/.././style.css', '/style.css'];
        yield ['/./css/style.css', '/css/style.css'];
        yield ['/../css/style.css', '/css/style.css'];
        yield ['/./../css/style.css', '/css/style.css'];
        yield ['/.././css/style.css', '/css/style.css'];
        yield ['/../../css/style.css', '/css/style.css'];

        // absolute paths (backslash, UNIX)
        yield ['\\css\\style.css', '/css/style.css'];
        yield ['\\css\\.\\style.css', '/css/style.css'];
        yield ['\\css\\..\\style.css', '/style.css'];
        yield ['\\css\\.\\..\\style.css', '/style.css'];
        yield ['\\css\\..\\.\\style.css', '/style.css'];
        yield ['\\.\\css\\style.css', '/css/style.css'];
        yield ['\\..\\css\\style.css', '/css/style.css'];
        yield ['\\.\\..\\css\\style.css', '/css/style.css'];
        yield ['\\..\\.\\css\\style.css', '/css/style.css'];
        yield ['\\..\\..\\css\\style.css', '/css/style.css'];

        // absolute paths (forward slash, Windows)
        yield ['C:/css/style.css', 'C:/css/style.css'];
        yield ['C:/css/./style.css', 'C:/css/style.css'];
        yield ['C:/css/../style.css', 'C:/style.css'];
        yield ['C:/css/./../style.css', 'C:/style.css'];
        yield ['C:/css/.././style.css', 'C:/style.css'];
        yield ['C:/./css/style.css', 'C:/css/style.css'];
        yield ['C:/../css/style.css', 'C:/css/style.css'];
        yield ['C:/./../css/style.css', 'C:/css/style.css'];
        yield ['C:/.././css/style.css', 'C:/css/style.css'];
        yield ['C:/../../css/style.css', 'C:/css/style.css'];

        // absolute paths (backslash, Windows)
        yield ['C:\\css\\style.css', 'C:/css/style.css'];
        yield ['C:\\css\\.\\style.css', 'C:/css/style.css'];
        yield ['C:\\css\\..\\style.css', 'C:/style.css'];
        yield ['C:\\css\\.\\..\\style.css', 'C:/style.css'];
        yield ['C:\\css\\..\\.\\style.css', 'C:/style.css'];
        yield ['C:\\.\\css\\style.css', 'C:/css/style.css'];
        yield ['C:\\..\\css\\style.css', 'C:/css/style.css'];
        yield ['C:\\.\\..\\css\\style.css', 'C:/css/style.css'];
        yield ['C:\\..\\.\\css\\style.css', 'C:/css/style.css'];
        yield ['C:\\..\\..\\css\\style.css', 'C:/css/style.css'];

        // Windows special case
        yield ['C:', 'C:/'];

        // Don't change malformed path
        yield ['C:css/style.css', 'C:css/style.css'];

        // absolute paths (stream, UNIX)
        yield ['phar:///css/style.css', 'phar:///css/style.css'];
        yield ['phar:///css/./style.css', 'phar:///css/style.css'];
        yield ['phar:///css/../style.css', 'phar:///style.css'];
        yield ['phar:///css/./../style.css', 'phar:///style.css'];
        yield ['phar:///css/.././style.css', 'phar:///style.css'];
        yield ['phar:///./css/style.css', 'phar:///css/style.css'];
        yield ['phar:///../css/style.css', 'phar:///css/style.css'];
        yield ['phar:///./../css/style.css', 'phar:///css/style.css'];
        yield ['phar:///.././css/style.css', 'phar:///css/style.css'];
        yield ['phar:///../../css/style.css', 'phar:///css/style.css'];

        // absolute paths (stream, Windows)
        yield ['phar://C:/css/style.css', 'phar://C:/css/style.css'];
        yield ['phar://C:/css/./style.css', 'phar://C:/css/style.css'];
        yield ['phar://C:/css/../style.css', 'phar://C:/style.css'];
        yield ['phar://C:/css/./../style.css', 'phar://C:/style.css'];
        yield ['phar://C:/css/.././style.css', 'phar://C:/style.css'];
        yield ['phar://C:/./css/style.css', 'phar://C:/css/style.css'];
        yield ['phar://C:/../css/style.css', 'phar://C:/css/style.css'];
        yield ['phar://C:/./../css/style.css', 'phar://C:/css/style.css'];
        yield ['phar://C:/.././css/style.css', 'phar://C:/css/style.css'];
        yield ['phar://C:/../../css/style.css', 'phar://C:/css/style.css'];
    }

    /**
     * @test
     * @dataProvider sanitizePathReturnsExpectedValueDataProvider
     */
    public function sanitizePathReturnsExpectedValue(string $path, string $expectedPath): void
    {
        $subject = new ComposerPackageManager();
        self::assertSame($expectedPath, $subject->sanitizePath($path));
    }

    /**
     * @test
     */
    public function coreExtensionCanBeResolvedByExtensionKey(): void
    {
        $subject = new ComposerPackageManager();
        $packageInfo = $subject->getPackageInfo('core');

        self::assertInstanceOf(PackageInfo::class, $packageInfo);
        self::assertSame('typo3/cms-core', $packageInfo->getName());
        self::assertSame('core', $packageInfo->getExtensionKey());
        self::assertTrue($packageInfo->isSystemExtension());
    }

    /**
     * @test
     */
    public function coreExtensionCanBeResolvedByPackageName(): void
    {
        $subject = new ComposerPackageManager();
        $packageInfo = $subject->getPackageInfo('typo3/cms-core');

        self::assertInstanceOf(PackageInfo::class, $packageInfo);
        self::assertSame('typo3/cms-core', $packageInfo->getName());
        self::assertSame('core', $packageInfo->getExtensionKey());
        self::assertTrue($packageInfo->isSystemExtension());
    }

    /**
     * @test
     */
    public function coreExtensionCanBeResolvedWithRelativeLegacyPathPrefix(): void
    {
        $subject = new ComposerPackageManager();
        $packageInfo = $subject->getPackageInfo('typo3/sysext/core');

        self::assertInstanceOf(PackageInfo::class, $packageInfo);
        self::assertSame('typo3/cms-core', $packageInfo->getName());
        self::assertSame('core', $packageInfo->getExtensionKey());
        self::assertTrue($packageInfo->isSystemExtension());
    }
}
