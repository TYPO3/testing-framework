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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
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

    #[DataProvider('sanitizePathReturnsExpectedValueDataProvider')]
    #[Test]
    public function sanitizePathReturnsExpectedValue(string $path, string $expectedPath): void
    {
        $subject = new ComposerPackageManager();
        self::assertSame($expectedPath, $subject->sanitizePath($path));
    }

    /**
     * @internal Ensure the TF related special case is in place as baseline for followup tests.
     */
    #[Test]
    public function testingFrameworkCanBeResolvedAsExtensionKey(): void
    {
        $subject = new ComposerPackageManager();
        $packageInfo = $subject->getPackageInfo('testing_framework');

        self::assertInstanceOf(PackageInfo::class, $packageInfo);
        self::assertSame('typo3/testing-framework', $packageInfo->getName());
        self::assertSame('', $packageInfo->getExtensionKey());
        self::assertFalse($packageInfo->isExtension());
        self::assertFalse($packageInfo->isSystemExtension());
        self::assertNull($packageInfo->getExtEmConf());
        self::assertNotNull($packageInfo->getInfo());
    }

    #[Test]
    public function coreExtensionCanBeResolvedByExtensionKey(): void
    {
        $subject = new ComposerPackageManager();
        $packageInfo = $subject->getPackageInfo('core');

        self::assertInstanceOf(PackageInfo::class, $packageInfo);
        self::assertSame('typo3/cms-core', $packageInfo->getName());
        self::assertSame('core', $packageInfo->getExtensionKey());
        self::assertTrue($packageInfo->isSystemExtension());
    }

    #[Test]
    public function coreExtensionCanBeResolvedByPackageName(): void
    {
        $subject = new ComposerPackageManager();
        $packageInfo = $subject->getPackageInfo('typo3/cms-core');

        self::assertInstanceOf(PackageInfo::class, $packageInfo);
        self::assertSame('typo3/cms-core', $packageInfo->getName());
        self::assertSame('core', $packageInfo->getExtensionKey());
        self::assertTrue($packageInfo->isSystemExtension());
    }

    #[Test]
    public function coreExtensionCanBeResolvedWithRelativeLegacyPathPrefix(): void
    {
        $subject = new ComposerPackageManager();
        $packageInfo = $subject->getPackageInfo('typo3/sysext/core');

        self::assertInstanceOf(PackageInfo::class, $packageInfo);
        self::assertSame('typo3/cms-core', $packageInfo->getName());
        self::assertSame('core', $packageInfo->getExtensionKey());
        self::assertTrue($packageInfo->isSystemExtension());
    }

    #[Test]
    public function extensionWithoutJsonCanBeResolvedByAbsolutePath(): void
    {
        $subject = new ComposerPackageManager();
        $packageInfo = $subject->getPackageInfoWithFallback(__DIR__ . '/Fixtures/Extensions/ext_without_composerjson_absolute');

        self::assertInstanceOf(PackageInfo::class, $packageInfo);
        self::assertSame('ext_without_composerjson_absolute', $packageInfo->getExtensionKey());
        self::assertSame('unknown-vendor/ext-without-composerjson-absolute', $packageInfo->getName());
        self::assertSame('typo3-cms-extension', $packageInfo->getType());
        self::assertNull($packageInfo->getInfo());
        self::assertNotNull($packageInfo->getExtEmConf());
    }

    #[Test]
    public function extensionWithoutJsonCanBeResolvedRelativeFromRoot(): void
    {
        $subject = new ComposerPackageManager();
        $packageInfo = $subject->getPackageInfoWithFallback('Tests/Unit/Composer/Fixtures/Extensions/ext_without_composerjson_relativefromroot');

        self::assertInstanceOf(PackageInfo::class, $packageInfo);
        self::assertSame('ext_without_composerjson_relativefromroot', $packageInfo->getExtensionKey());
        self::assertSame('unknown-vendor/ext-without-composerjson-relativefromroot', $packageInfo->getName());
        self::assertSame('typo3-cms-extension', $packageInfo->getType());
        self::assertNull($packageInfo->getInfo());
        self::assertNotNull($packageInfo->getExtEmConf());
    }

    #[Test]
    public function extensionWithoutJsonCanBeResolvedByLegacyPath(): void
    {
        $subject = new ComposerPackageManager();
        $packageInfo = $subject->getPackageInfoWithFallback('typo3conf/ext/testing_framework/Tests/Unit/Composer/Fixtures/Extensions/ext_without_composerjson_fallbackroot');

        self::assertInstanceOf(PackageInfo::class, $packageInfo);
        self::assertSame('ext_without_composerjson_fallbackroot', $packageInfo->getExtensionKey());
        self::assertSame('unknown-vendor/ext-without-composerjson-fallbackroot', $packageInfo->getName());
        self::assertSame('typo3-cms-extension', $packageInfo->getType());
        self::assertNull($packageInfo->getInfo());
        self::assertNotNull($packageInfo->getExtEmConf());
    }

    #[Test]
    public function extensionWithJsonCanBeResolvedByAbsolutePath(): void
    {
        $subject = new ComposerPackageManager();
        $packageInfo = $subject->getPackageInfoWithFallback(__DIR__ . '/Fixtures/Extensions/ext_absolute');

        self::assertInstanceOf(PackageInfo::class, $packageInfo);
        self::assertSame('absolute_real', $packageInfo->getExtensionKey());
        self::assertSame('testing-framework/extension-absolute', $packageInfo->getName());
        self::assertSame('typo3-cms-extension', $packageInfo->getType());
        self::assertNotNull($packageInfo->getInfo());
        self::assertNotNull($packageInfo->getExtEmConf());
    }

    #[Test]
    public function extensionWithJsonCanBeResolvedRelativeFromRoot(): void
    {
        $subject = new ComposerPackageManager();
        $packageInfo = $subject->getPackageInfoWithFallback('Tests/Unit/Composer/Fixtures/Extensions/ext_relativefromroot');

        self::assertInstanceOf(PackageInfo::class, $packageInfo);
        self::assertSame('relativefromroot_real', $packageInfo->getExtensionKey());
        self::assertSame('testing-framework/extension-relativefromroot', $packageInfo->getName());
        self::assertSame('typo3-cms-extension', $packageInfo->getType());
        self::assertNotNull($packageInfo->getInfo());
        self::assertNotNull($packageInfo->getExtEmConf());
    }

    #[Test]
    public function extensionWithJsonCanBeResolvedByLegacyPath(): void
    {
        $subject = new ComposerPackageManager();
        $packageInfo = $subject->getPackageInfoWithFallback('typo3conf/ext/testing_framework/Tests/Unit/Composer/Fixtures/Extensions/ext_fallbackroot');

        self::assertInstanceOf(PackageInfo::class, $packageInfo);
        self::assertSame('fallbackroot_real', $packageInfo->getExtensionKey());
        self::assertSame('testing-framework/extension-fallbackroot', $packageInfo->getName());
        self::assertSame('typo3-cms-extension', $packageInfo->getType());
        self::assertNotNull($packageInfo->getInfo());
        self::assertNotNull($packageInfo->getExtEmConf());
    }

    #[Test]
    public function extensionWithJsonCanBeResolvedByRelativeLegacyPath(): void
    {
        $subject = new ComposerPackageManager();
        $projectFolderName = basename($subject->getRootPath());
        $packageInfo = $subject->getPackageInfoWithFallback('../' . $projectFolderName . '/typo3conf/ext/testing_framework/Tests/Unit/Composer/Fixtures/Extensions/ext_fallbackroot');

        self::assertInstanceOf(PackageInfo::class, $packageInfo);
        self::assertSame('fallbackroot_real', $packageInfo->getExtensionKey());
        self::assertSame('testing-framework/extension-fallbackroot', $packageInfo->getName());
        self::assertSame('typo3-cms-extension', $packageInfo->getType());
        self::assertNotNull($packageInfo->getInfo());
        self::assertNotNull($packageInfo->getExtEmConf());
    }

    public static function packagesWithoutExtEmConfFileDataProvider(): \Generator
    {
        yield 'package0 => package0' => [
            'path' => __DIR__ . '/../Fixtures/Packages/package0',
            'expectedExtensionKey' => 'package0',
            'expectedPackageName' => 'typo3/testing-framework-package-0',
        ];
        yield 'package0 => package1' => [
            'path' => __DIR__ . '/../Fixtures/Packages/package1',
            'expectedExtensionKey' => 'package1',
            'expectedPackageName' => 'typo3/testing-framework-package-1',
        ];
        yield 'package0 => package2' => [
            'path' => __DIR__ . '/../Fixtures/Packages/package2',
            'expectedExtensionKey' => 'package2',
            'expectedPackageName' => 'typo3/testing-framework-package-2',
        ];
        yield 'package-identifier => some_test_extension' => [
            'path' => __DIR__ . '/../Fixtures/Packages/package-identifier',
            'expectedExtensionKey' => 'some_test_extension',
            'expectedPackageName' => 'typo3/testing-framework-package-identifier',
        ];
    }

    #[DataProvider('packagesWithoutExtEmConfFileDataProvider')]
    #[Test]
    public function getPackageInfoWithFallbackReturnsExtensionInfoWithCorrectExtensionKeyWhenNotHavingAnExtEmConfFile(
        string $path,
        string $expectedExtensionKey,
        string $expectedPackageName,
    ): void {
        $packageInfo = (new ComposerPackageManager())->getPackageInfoWithFallback($path);
        self::assertInstanceOf(PackageInfo::class, $packageInfo, 'PackageInfo retrieved for ' . $path);
        self::assertNull($packageInfo->getExtEmConf(), 'Package provides ext_emconf.php');
        self::assertNotNull($packageInfo->getInfo(), 'Package has no composer info (composer.json)');
        self::assertIsArray($packageInfo->getInfo(), 'Package composer info is not an array');
        self::assertNotEmpty($packageInfo->getInfo(), 'Package composer info is empty');
        self::assertTrue($packageInfo->isExtension(), 'Package is not a extension');
        self::assertFalse($packageInfo->isSystemExtension(), 'Package is a system extension');
        self::assertTrue($packageInfo->isComposerPackage(), 'Package is not a composer package');
        self::assertFalse($packageInfo->isMonoRepository(), 'Package is mono repository');
        self::assertSame($expectedPackageName, $packageInfo->getName());
        self::assertSame($expectedExtensionKey, $packageInfo->getExtensionKey());
    }

    #[Test]
    public function getPackageInfoWithFallbackReturnsExtensionInfoWithCorrectExtensionKeyAndHavingAnExtEmConfFile(): void
    {
        $path = __DIR__ . '/../Fixtures/Packages/package-with-extemconf';
        $expectedExtensionKey = 'extension_with_extemconf';
        $expectedPackageName = 'typo3/testing-framework-package-with-extemconf';
        $packageInfo = (new ComposerPackageManager())->getPackageInfoWithFallback($path);
        self::assertInstanceOf(PackageInfo::class, $packageInfo, 'PackageInfo retrieved for ' . $path);
        self::assertNotNull($packageInfo->getExtEmConf(), 'Package has ext_emconf.php file');
        self::assertNotNull($packageInfo->getInfo(), 'Package has composer info');
        self::assertIsArray($packageInfo->getInfo(), 'Package composer info is an array');
        self::assertNotEmpty($packageInfo->getInfo(), 'Package composer info is not empty');
        self::assertTrue($packageInfo->isExtension(), 'Package is a extension');
        self::assertFalse($packageInfo->isSystemExtension(), 'Package is not a system extension');
        self::assertTrue($packageInfo->isComposerPackage(), 'Package is a composer package');
        self::assertFalse($packageInfo->isMonoRepository(), 'Package is not monorepository root');
        self::assertSame($expectedPackageName, $packageInfo->getName());
        self::assertSame($expectedExtensionKey, $packageInfo->getExtensionKey());
    }
}
