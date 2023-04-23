<?php

declare(strict_types=1);

namespace TYPO3\TestingFramework\Composer;

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

use Composer\InstalledVersions;
use Symfony\Component\Filesystem\Path;

/**
 * @internal This class is for testing-framework internal processing and not part of public testing API.
 */
final class ComposerPackageManager
{
    private static string $vendorPath = '';

    private static ?PackageInfo $rootPackage = null;

    /**
     * @var array<string, PackageInfo>
     */
    private static array $packages = [];

    /**
     * @var array<non-empty-string, non-empty-string>
     */
    private static array $extensionKeyToPackageNameMap = [];

    public function __construct()
    {
        $this->build();
    }

    public function getPackageInfo(string $name): ?PackageInfo
    {
        $name = $this->resolvePackageName($name);
        return self::$packages[$name] ?? null;
    }

    /**
     * Get list of system extensions keys. We need this as fallback if no core extensions are selected to be symlinked.
     *
     * @return string[]
     */
    public function getSystemExtensionExtensionKeys(): array
    {
        $extensionKeys = [];
        foreach (self::$packages as $packageInfo) {
            if ($packageInfo->isSystemExtension()
                && $packageInfo->getExtensionKey() !== ''
            ) {
                $extensionKeys[] = $packageInfo->getExtensionKey();
            }
        }
        return $extensionKeys;
    }

    /**
     * Get full vendor path
     */
    public function getVendorPath(): string
    {
        return self::$vendorPath;
    }

    /**
     * Build package caches if not already done.
     */
    private function build(): void
    {
        if (self::$rootPackage instanceof PackageInfo) {
            return;
        }

        $this->processRootPackage();
        $this->processMonoRepository();
        $this->processPackages();
    }

    /**
     * Extract root package information. This must be done first, to have related information at hand for subsequent
     * package information retrieval.
     */
    private function processRootPackage(): void
    {
        $package = InstalledVersions::getRootPackage();
        $packageName = $package['name'];
        $packagePath = $this->getPackageInstallPath($packageName);
        $packageRealPath = $this->realPath($packagePath);
        $info = $this->getPackageComposerJson($packagePath) ?? [];
        $packageType = $info['type'] ?? '';

        $packageInfo = new PackageInfo(
            $packageName,
            $packageType,
            $packagePath,
            $packageRealPath,
            $package['pretty_version'],
            $info
        );
        self::$rootPackage = $packageInfo;
        $this->addPackageInfo($packageInfo);

        self::$vendorPath = $this->realPath(
            rtrim(
                $packageInfo->getRealPath() . '/' . ($packageInfo->getVendorDir() ?: 'vendor'),
                '/'
            )
        );
    }

    /**
     * TYPO3 Core Development Mono Repository has a special setup, where the system extension are not required by the
     * root composer.json. Therefore, we need to look them up manually to add corresponding package information. This
     * allows us to handle system extensions in mono repository they same way as outside and make e.g. symlink system
     * extensions to test instance simpler by eliminating the need for dedicated mono-repository handling there.
     */
    private function processMonoRepository(): void
    {
        if (!$this->rootPackage()->isMonoRepository()) {
            return;
        }

        $systemExtensionComposerJsonFiles = glob($this->rootPackage()->getRealPath() . '/typo3/sysext/*/composer.json');
        foreach ($systemExtensionComposerJsonFiles as $systemExtensionComposerJsonFile) {
            $packagePath = dirname($systemExtensionComposerJsonFile);
            $packageRealPath = $this->realPath($packagePath);
            $info = $this->getPackageComposerJson($packageRealPath);
            $packageName = $info['name'] ?? '';
            $packageType = $info['type'] ?? '';
            $packageInfo = new PackageInfo(
                $packageName,
                $packageType,
                $packagePath,
                $packageRealPath,
                // System extensions in mono-repository are exactly the same version as the root package. Use it.
                $this->rootPackage()->getVersion(),
                $info
            );
            if (!$packageInfo->isSystemExtension()) {
                continue;
            }
            $this->addPackageInfo($packageInfo);
        }
    }

    /**
     * Process all composer installed packages.
     */
    private function processPackages(): void
    {
        foreach (InstalledVersions::getAllRawData() as $loader) {
            foreach ($loader['versions'] as $packageName => $version) {
                $packagePath = $this->getPackageInstallPath($packageName);
                $packageRealPath = $this->realPath($packagePath);
                $info = $this->getPackageComposerJson($packagePath) ?? [];
                $packageType = $info['type'] ?? '';
                $this->addPackageInfo(new PackageInfo(
                    $packageName,
                    $packageType,
                    $packagePath,
                    $packageRealPath,
                    (string)($version['pretty_version'] ?? ''),
                    $info
                ));
            }
        }
    }

    /**
     * Adds the package information to the internal cache. Additionally, it sets the extensionKey to packageName
     * map information, if a TYPO3 extension or system-extensions package information is handed over. This map
     * is used to allow extensionKey or packageName for retrieving package information, which comes in handy to
     * provide backward compatibility for test core- and test extension symlink configuration per test instance.
     */
    private function addPackageInfo(PackageInfo $packageInfo): void
    {
        if (self::$packages[$packageInfo->getName()] ?? null) {
            return;
        }
        self::$packages[$packageInfo->getName()] = $packageInfo;
        if ($packageInfo->getExtensionKey() !== '') {
            self::$extensionKeyToPackageNameMap[$packageInfo->getExtensionKey()] = $packageInfo->getName();
        }
    }

    private function rootPackage(): ?PackageInfo
    {
        return self::$rootPackage;
    }

    private function getPackageComposerJson(string $path): ?array
    {
        $composerFile = rtrim($path, '/') . '/composer.json';
        if (!file_exists($composerFile) || !is_readable($composerFile)) {
            return null;
        }
        try {
            return json_decode((string)file_get_contents($composerFile), true, JSON_THROW_ON_ERROR);
        } catch(\Throwable $t) {
            // skipped
        }
        return null;
    }

    private function resolvePackageName(string $name): string
    {
        if (str_starts_with($name, 'typo3conf/ext/')
            || str_starts_with($name, 'typo3/sysext/')
        ) {
            $name = basename($name);
        }
        return self::$extensionKeyToPackageNameMap[$name] ?? $name;
    }

    /**
     * Get the sanitized package installation path.
     *
     * Note: Not using realpath() is done by intention. That gives us the ability, to eventually avoid duplicates and
     *       act on both paths if needed.
     */
    private function getPackageInstallPath(string $name): string
    {
        return $this->sanitizePath((string)InstalledVersions::getInstallPath($name));
    }

    /**
     * This method resolves relative path tokens directly ( e.g. '/../' ) and sanitizes the path from back-slash to
     * slash for a cross os compatibility.
     */
    private function sanitizePath(string $path): string
    {
        return Path::canonicalize(rtrim(strtr($path, '\\', '/'), '/'));
    }

    /**
     * Guarded realpath() wrapper, to ensure we do not lose an path information if realpath() would fail.
     */
    private function realPath(string $path): string
    {
        $path = $this->sanitizePath($path);
        return realpath($path) ?: $path;
    }
}
