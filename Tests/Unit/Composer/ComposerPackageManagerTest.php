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
        $extensionMapPropertyReflection = new \ReflectionProperty($subject, 'extensionKeyToPackageNameMap');
        self::assertIsArray($extensionMapPropertyReflection->getValue($subject));
        $packageInfo = $subject->getPackageInfoWithFallback(__DIR__ . '/Fixtures/Extensions/ext_without_composerjson_absolute');

        // Extension without composer.json registers basefolder as extension key
        self::assertArrayHasKey('ext_without_composerjson_absolute', $extensionMapPropertyReflection->getValue($subject));
        self::assertSame('unknown-vendor/ext-without-composerjson-absolute', $extensionMapPropertyReflection->getValue($subject)['ext_without_composerjson_absolute']);

        // Verify package info
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
        $extensionMapPropertyReflection = new \ReflectionProperty($subject, 'extensionKeyToPackageNameMap');
        self::assertIsArray($extensionMapPropertyReflection->getValue($subject));
        $packageInfo = $subject->getPackageInfoWithFallback('Tests/Unit/Composer/Fixtures/Extensions/ext_without_composerjson_relativefromroot');

        // Extension without composer.json registers basefolder as extension key
        self::assertArrayHasKey('ext_without_composerjson_relativefromroot', $extensionMapPropertyReflection->getValue($subject));
        self::assertSame('unknown-vendor/ext-without-composerjson-relativefromroot', $extensionMapPropertyReflection->getValue($subject)['ext_without_composerjson_relativefromroot']);

        // Verify package info
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
        $extensionMapPropertyReflection = new \ReflectionProperty($subject, 'extensionKeyToPackageNameMap');
        self::assertIsArray($extensionMapPropertyReflection->getValue($subject));
        $packageInfo = $subject->getPackageInfoWithFallback('typo3conf/ext/testing_framework/Tests/Unit/Composer/Fixtures/Extensions/ext_without_composerjson_fallbackroot');

        // Extension without composer.json registers basefolder as extension key
        self::assertArrayHasKey('ext_without_composerjson_fallbackroot', $extensionMapPropertyReflection->getValue($subject));
        self::assertSame('unknown-vendor/ext-without-composerjson-fallbackroot', $extensionMapPropertyReflection->getValue($subject)['ext_without_composerjson_fallbackroot']);

        // Verify package info
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
        $extensionMapPropertyReflection = new \ReflectionProperty($subject, 'extensionKeyToPackageNameMap');
        self::assertIsArray($extensionMapPropertyReflection->getValue($subject));
        $packageInfo = $subject->getPackageInfoWithFallback(__DIR__ . '/Fixtures/Extensions/ext_absolute');

        // Extension with composer.json and extension key does not register basepath as extension key
        self::assertArrayNotHasKey('ext_absolute', $extensionMapPropertyReflection->getValue($subject));

        // Extension with composer.json and extension key register extension key as composer package alias
        self::assertArrayHasKey('absolute_real', $extensionMapPropertyReflection->getValue($subject));
        self::assertSame('testing-framework/extension-absolute', $extensionMapPropertyReflection->getValue($subject)['absolute_real']);

        // Verify package info
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
        $extensionMapPropertyReflection = new \ReflectionProperty($subject, 'extensionKeyToPackageNameMap');
        self::assertIsArray($extensionMapPropertyReflection->getValue($subject));
        $packageInfo = $subject->getPackageInfoWithFallback('Tests/Unit/Composer/Fixtures/Extensions/ext_relativefromroot');

        // Extension with composer.json and extension key does not register basepath as extension key
        self::assertArrayNotHasKey('ext_relativefromroot', $extensionMapPropertyReflection->getValue($subject));

        // Extension with composer.json and extension key register extension key as composer package alias
        self::assertArrayHasKey('relativefromroot_real', $extensionMapPropertyReflection->getValue($subject));
        self::assertSame('testing-framework/extension-relativefromroot', $extensionMapPropertyReflection->getValue($subject)['relativefromroot_real']);

        // Verify package info
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
        $extensionMapPropertyReflection = new \ReflectionProperty($subject, 'extensionKeyToPackageNameMap');
        self::assertIsArray($extensionMapPropertyReflection->getValue($subject));
        $packageInfo = $subject->getPackageInfoWithFallback('typo3conf/ext/testing_framework/Tests/Unit/Composer/Fixtures/Extensions/ext_fallbackroot');

        // Extension with composer.json and extension key does not register basepath as extension key
        self::assertArrayNotHasKey('ext_fallbackroot', $extensionMapPropertyReflection->getValue($subject));

        // Extension with composer.json and extension key register extension key as composer package alias
        self::assertArrayHasKey('fallbackroot_real', $extensionMapPropertyReflection->getValue($subject));
        self::assertSame('testing-framework/extension-fallbackroot', $extensionMapPropertyReflection->getValue($subject)['fallbackroot_real']);

        // Verify package info
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
        $extensionMapPropertyReflection = new \ReflectionProperty($subject, 'extensionKeyToPackageNameMap');
        self::assertIsArray($extensionMapPropertyReflection->getValue($subject));
        $projectFolderName = basename($subject->getRootPath());
        $packageInfo = $subject->getPackageInfoWithFallback('../' . $projectFolderName . '/typo3conf/ext/testing_framework/Tests/Unit/Composer/Fixtures/Extensions/ext_fallbackroot');

        // Extension with composer.json and extension key does not register basepath as extension key
        self::assertArrayNotHasKey('ext_fallbackroot', $extensionMapPropertyReflection->getValue($subject));

        // Extension with composer.json and extension key register extension key as composer package alias
        self::assertArrayHasKey('fallbackroot_real', $extensionMapPropertyReflection->getValue($subject));
        self::assertSame('testing-framework/extension-fallbackroot', $extensionMapPropertyReflection->getValue($subject)['fallbackroot_real']);

        // Verify package info
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
        self::assertNotEmpty($packageInfo->getInfo(), 'Package composer info is not empty');
        self::assertTrue($packageInfo->isExtension(), 'Package is a extension');
        self::assertFalse($packageInfo->isSystemExtension(), 'Package is not a system extension');
        self::assertTrue($packageInfo->isComposerPackage(), 'Package is a composer package');
        self::assertFalse($packageInfo->isMonoRepository(), 'Package is not mono repository root');
        self::assertSame($expectedPackageName, $packageInfo->getName());
        self::assertSame($expectedExtensionKey, $packageInfo->getExtensionKey());
    }

    public static function prepareResolvePackageNameReturnsExpectedValuesDataProvider(): \Generator
    {
        yield 'Composer package name returns unchanged (not checked for existence)' => [
            'name' => 'typo3/cms-core',
            'expected' => 'typo3/cms-core',
        ];
        yield 'Extension key returns unchanged (not checked for existence)' => [
            'name' => 'core',
            'expected' => 'core',
        ];
        yield 'Classic mode system path returns extension key (not checked for existence)' => [
            'name' => 'typo3/sysext/core',
            'expected' => 'core',
        ];
        yield 'Classic mode extension path returns extension key (not checked for existence)' => [
            'name' => 'typo3conf/ext/some_ext',
            'expected' => 'some_ext',
        ];
        yield 'Not existing full path to classic system extension path resolves to extension key (not checked for existence)' => [
            'name' => 'ROOT:/typo3/sysext/core',
            'expected' => 'core',
        ];
        yield 'Not existing full path to classic extension path resolves to extension key (not checked for existence)' => [
            'name' => 'ROOT:/typo3conf/ext/some_ext',
            'expected' => 'some_ext',
        ];
        yield 'Vendor path returns vendor with package subfolder' => [
            'name' => 'VENDOR:/typo3/cms-core',
            'expected' => 'typo3/cms-core',
        ];
    }

    #[DataProvider('prepareResolvePackageNameReturnsExpectedValuesDataProvider')]
    #[Test]
    public function prepareResolvePackageNameReturnsExpectedValues(string $name, string $expected): void
    {
        $composerPackageManager = new ComposerPackageManager();
        $replaceMap = [
            'ROOT:/' => rtrim($composerPackageManager->getRootPath(), '/') . '/',
            'VENDOR:/' => rtrim($composerPackageManager->getVendorPath(), '/') . '/',
        ];
        $name = str_replace(array_keys($replaceMap), array_values($replaceMap), $name);
        foreach (array_keys($replaceMap) as $replaceKey) {
            self::assertStringNotContainsString($replaceKey, $name, 'Key "%s" is replaced in name "%s"');
        }
        $prepareResolvePackageNameReflectionMethod = new \ReflectionMethod($composerPackageManager, 'prepareResolvePackageName');
        $resolved = $prepareResolvePackageNameReflectionMethod->invoke($composerPackageManager, $name);
        self::assertSame($expected, $resolved, sprintf('"%s" resolved to "%s"', $name, $expected));
    }

    public static function resolvePackageNameReturnsExpectedPackageNameDataProvider(): \Generator
    {
        yield 'Composer package name returns unchanged (not checked for existence)' => [
            'name' => 'typo3/cms-core',
            'expected' => 'typo3/cms-core',
        ];
        yield 'Extension key returns unchanged (not checked for existence)' => [
            'name' => 'core',
            'expected' => 'typo3/cms-core',
        ];
        yield 'Classic mode system path returns extension key (not checked for existence)' => [
            'name' => 'typo3/sysext/core',
            'expected' => 'typo3/cms-core',
        ];
        yield 'Not existing full path to classic system extension path resolves to extension key (not checked for existence)' => [
            'name' => 'ROOT:/typo3/sysext/core',
            'expected' => 'typo3/cms-core',
        ];
        yield 'Vendor path returns vendor with package subfolder' => [
            'name' => 'VENDOR:/typo3/cms-core',
            'expected' => 'typo3/cms-core',
        ];
        // Not loaded/known extension resolves only extension key and not to a composer package name.
        yield 'Not existing full path to classic extension path resolves to extension key for unknown extension' => [
            'name' => 'ROOT:/typo3conf/ext/some_ext',
            'expected' => 'some_ext',
        ];
        // Not loaded/known extension resolves only extension key and not to a composer package name.
        yield 'Classic mode extension path returns extension key for unknown extension' => [
            'name' => 'typo3conf/ext/some_ext',
            'expected' => 'some_ext',
        ];
    }

    #[DataProvider('resolvePackageNameReturnsExpectedPackageNameDataProvider')]
    #[Test]
    public function resolvePackageNameReturnsExpectedPackageName(string $name, string $expected): void
    {
        $composerPackageManager = new ComposerPackageManager();
        $replaceMap = [
            'ROOT:/' => rtrim($composerPackageManager->getRootPath(), '/') . '/',
            'VENDOR:/' => rtrim($composerPackageManager->getVendorPath(), '/') . '/',
        ];
        $name = str_replace(array_keys($replaceMap), array_values($replaceMap), $name);
        foreach (array_keys($replaceMap) as $replaceKey) {
            self::assertStringNotContainsString($replaceKey, $name, 'Key "%s" is replaced in name "%s"');
        }
        $resolvePackageNameReflectionMethod = new \ReflectionMethod($composerPackageManager, 'resolvePackageName');
        $resolved = $resolvePackageNameReflectionMethod->invoke($composerPackageManager, $name);
        self::assertSame($expected, $resolved, sprintf('"%s" resolved to "%s"', $name, $expected));
    }

    #[Test]
    public function ensureEndingComposerPackageNameAndTypoExtensionPackageExtensionKeyResolvesCorrectPackage(): void
    {
        $composerManager = new ComposerPackageManager();
        $extensionMapPropertyReflection = new \ReflectionProperty($composerManager, 'extensionKeyToPackageNameMap');
        self::assertIsArray($extensionMapPropertyReflection->getValue($composerManager));

        // verify initial composer package information
        $initComposerPackage = $composerManager->getPackageInfoWithFallback(__DIR__ . '/Fixtures/Packages/sharedextensionkey');
        self::assertArrayNotHasKey('sharedextensionkey', $extensionMapPropertyReflection->getValue($composerManager));
        self::assertInstanceOf(PackageInfo::class, $initComposerPackage);
        self::assertSame('testing-framework/sharedextensionkey', $initComposerPackage->getName(), 'PackageInfo->name is "testing-framework/sharedextensionkey"');
        self::assertFalse($initComposerPackage->isSystemExtension(), '"testing-framework/sharedextensionkey" is not a TYPO3 system extension');
        self::assertFalse($initComposerPackage->isExtension(), '"testing-framework/sharedextensionkey" is not a TYPO3 extension');
        self::assertTrue($initComposerPackage->isComposerPackage(), '"testing-framework/sharedextensionkey" is a composer package');
        self::assertSame('', $initComposerPackage->getExtensionKey());

        // verify initial extension package information
        $initExtensionPackage = $composerManager->getPackageInfoWithFallback(__DIR__ . '/Fixtures/Extensions/extension-key-shared-with-composer-package');
        self::assertArrayHasKey('sharedextensionkey', $extensionMapPropertyReflection->getValue($composerManager));
        self::assertSame('testing-framework/extension-key-shared-with-composer-package', $extensionMapPropertyReflection->getValue($composerManager)['sharedextensionkey']);
        self::assertInstanceOf(PackageInfo::class, $initExtensionPackage);
        self::assertSame('testing-framework/extension-key-shared-with-composer-package', $initExtensionPackage->getName(), 'PackageInfo->name is "testing-framework/extension-key-shared-with-composer-package"');
        self::assertFalse($initExtensionPackage->isSystemExtension(), '"testing-framework/extension-key-shared-with-composer-package" is not a TYPO3 system extension');
        self::assertTrue($initExtensionPackage->isExtension(), '"testing-framework/extension-key-shared-with-composer-package" is not a TYPO3 extension');
        self::assertTrue($initExtensionPackage->isComposerPackage(), '"testing-framework/extension-key-shared-with-composer-package" is a composer package');
        self::assertSame('sharedextensionkey', $initExtensionPackage->getExtensionKey());

        // verify shared extension key retrieval returns the extension package
        $extensionPackage = $composerManager->getPackageInfo('sharedextensionkey');
        self::assertInstanceOf(PackageInfo::class, $extensionPackage);
        self::assertSame('testing-framework/extension-key-shared-with-composer-package', $extensionPackage->getName(), 'PackageInfo->name is "testing-framework/extension-key-shared-with-composer-package"');
        self::assertFalse($extensionPackage->isSystemExtension(), '"testing-framework/extension-key-shared-with-composer-package" is not a TYPO3 system extension');
        self::assertTrue($extensionPackage->isExtension(), '"testing-framework/extension-key-shared-with-composer-package" is not a TYPO3 extension');
        self::assertTrue($extensionPackage->isComposerPackage(), '"testing-framework/extension-key-shared-with-composer-package" is a composer package');
        self::assertSame('sharedextensionkey', $extensionPackage->getExtensionKey());

        // verify shared extension key with classic mode prefix retrieval returns the extension package
        $classicModeExtensionPackage = $composerManager->getPackageInfo('typo3conf/ext/sharedextensionkey');
        self::assertInstanceOf(PackageInfo::class, $classicModeExtensionPackage);
        self::assertSame('testing-framework/extension-key-shared-with-composer-package', $classicModeExtensionPackage->getName(), 'PackageInfo->name is "testing-framework/extension-key-shared-with-composer-package"');
        self::assertFalse($classicModeExtensionPackage->isSystemExtension(), '"testing-framework/extension-key-shared-with-composer-package" is not a TYPO3 system extension');
        self::assertTrue($classicModeExtensionPackage->isExtension(), '"testing-framework/extension-key-shared-with-composer-package" is not a TYPO3 extension');
        self::assertTrue($classicModeExtensionPackage->isComposerPackage(), '"testing-framework/extension-key-shared-with-composer-package" is a composer package');
        self::assertSame('sharedextensionkey', $classicModeExtensionPackage->getExtensionKey());
    }

    /**
     * @todo Remove this when fluid/standalone fluid is no longer available by default due to core dependencies.
     * {@see ensureEndingComposerPackageNameAndTypoExtensionPackageExtensionKeyResolvesCorrectPackage}
     */
    #[Test]
    public function ensureStandaloneFluidDoesNotBreakCoreFluidExtension(): void
    {
        $composerManager = new ComposerPackageManager();

        // Verify standalone fluid composer package
        $standaloneFluid = $composerManager->getPackageInfo('typo3fluid/fluid');
        self::assertInstanceOf(PackageInfo::class, $standaloneFluid);
        self::assertSame('typo3fluid/fluid', $standaloneFluid->getName(), 'PackageInfo->name is not "typo3fluid/fluid"');
        self::assertFalse($standaloneFluid->isSystemExtension(), '"typo3fluid/fluid" is not a TYPO3 system extension');
        self::assertFalse($standaloneFluid->isExtension(), '"typo3fluid/fluid" is not a TYPO3 extension');
        self::assertTrue($standaloneFluid->isComposerPackage(), '"typo3fluid/fluid" is a composer package');
        self::assertSame('', $standaloneFluid->getExtensionKey());

        // Verify TYPO3 system extension fluid.
        $coreFluid = $composerManager->getPackageInfo('typo3/cms-fluid');
        self::assertInstanceOf(PackageInfo::class, $coreFluid);
        self::assertSame('typo3/cms-fluid', $coreFluid->getName(), 'PackageInfo->name is not "typo3/cms-fluid"');
        self::assertTrue($coreFluid->isSystemExtension(), '"typo3/cms-fluid" is a TYPO3 system extension');
        self::assertFalse($coreFluid->isExtension(), '"typo3/cms-fluid" is not a TYPO3 extension');
        self::assertTrue($coreFluid->isComposerPackage(), '"typo3/cms-fluid" is a composer package');
        self::assertSame('fluid', $coreFluid->getExtensionKey());

        // Verify TYPO3 system extension fluid resolved using extension key.
        $extensionKeyRetrievesCoreFluid = $composerManager->getPackageInfo('fluid');
        self::assertInstanceOf(PackageInfo::class, $extensionKeyRetrievesCoreFluid);
        self::assertSame('typo3/cms-fluid', $extensionKeyRetrievesCoreFluid->getName(), 'PackageInfo->name is not "typo3/cms-fluid"');
        self::assertTrue($extensionKeyRetrievesCoreFluid->isSystemExtension(), '"typo3/cms-fluid" is a TYPO3 system extension');
        self::assertFalse($extensionKeyRetrievesCoreFluid->isExtension(), '"typo3/cms-fluid" is not a TYPO3 extension');
        self::assertTrue($extensionKeyRetrievesCoreFluid->isComposerPackage(), '"typo3/cms-fluid" is a composer package');
        self::assertSame('fluid', $extensionKeyRetrievesCoreFluid->getExtensionKey());

        // Verify TYPO3 system extension fluid resolved using relative classic mode path.
        $extensionRelativeSystemExtensionPath = $composerManager->getPackageInfo('typo3/sysext/fluid');
        self::assertInstanceOf(PackageInfo::class, $extensionRelativeSystemExtensionPath);
        self::assertSame('typo3/cms-fluid', $extensionRelativeSystemExtensionPath->getName(), 'PackageInfo->name is not "typo3/cms-fluid"');
        self::assertTrue($extensionRelativeSystemExtensionPath->isSystemExtension(), '"typo3/cms-fluid" is a TYPO3 system extension');
        self::assertFalse($extensionRelativeSystemExtensionPath->isExtension(), '"typo3/cms-fluid" is not a TYPO3 extension');
        self::assertTrue($extensionRelativeSystemExtensionPath->isComposerPackage(), '"typo3/cms-fluid" is a composer package');
        self::assertSame('fluid', $extensionRelativeSystemExtensionPath->getExtensionKey());
    }
}
