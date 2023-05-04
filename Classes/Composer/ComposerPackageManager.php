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

/**
 * @internal This class is for testing-framework internal processing and not part of public testing API.
 */
final class ComposerPackageManager
{
    /**
     * The number of buffer entries that triggers a cleanup operation.
     */
    private const CLEANUP_THRESHOLD = 1250;

    /**
     * The buffer size after the cleanup operation.
     */
    private const CLEANUP_SIZE = 1000;

    /**
     * Buffers input/output of {@link canonicalize()}.
     *
     * @var array<string, string>
     */
    private static $buffer = [];

    private static $bufferSize = 0;

    private static string $vendorPath = '';

    private static PackageInfo|null $rootPackage = null;

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
        if (! ($this->rootPackage()?->isMonoRepository() ?? false)) {
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
        } catch(\Throwable) {
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
    public function sanitizePath(string $path): string
    {
        return $this->canonicalize(rtrim(strtr($path, '\\', '/'), '/'));
    }

    /**
     * Guarded realpath() wrapper, to ensure we do not lose an path information if realpath() would fail.
     */
    private function realPath(string $path): string
    {
        $path = $this->sanitizePath($path);
        return realpath($path) ?: $path;
    }

    private function canonicalize(string $path): string
    {
        if ($path === '') {
            return '';
        }

        // This method is called by many other methods in this class. Buffer
        // the canonicalized paths to make up for the severe performance
        // decrease.
        if (isset(self::$buffer[$path])) {
            return self::$buffer[$path];
        }

        $path = str_replace('\\', '/', $path);

        [$root, $pathWithoutRoot] = $this->split($path);

        $canonicalParts = $this->findCanonicalParts($root, $pathWithoutRoot);

        // Add the root directory again
        self::$buffer[$path] = $canonicalPath = $root . implode('/', $canonicalParts);
        ++self::$bufferSize;

        // Clean up regularly to prevent memory leaks
        if (self::$bufferSize > self::CLEANUP_THRESHOLD) {
            self::$buffer = \array_slice(self::$buffer, -self::CLEANUP_SIZE, null, true);
            self::$bufferSize = self::CLEANUP_SIZE;
        }

        return $canonicalPath;
    }

    private function split(string $path): array
    {
        if ($path === '') {
            return ['', ''];
        }

        // Remember scheme as part of the root, if any
        $schemeSeparatorPosition = strpos($path, '://');
        if ($schemeSeparatorPosition !== false) {
            $root = substr($path, 0, $schemeSeparatorPosition + 3);
            $path = substr($path, $schemeSeparatorPosition + 3);
        } else {
            $root = '';
        }

        $length = \strlen($path);

        // Remove and remember root directory
        if (str_starts_with($path, '/')) {
            $root .= '/';
            $path = $length > 1 ? substr($path, 1) : '';
        } elseif ($length > 1 && ctype_alpha($path[0]) && ':' === $path[1]) {
            if ($length === 2) {
                // Windows special case: "C:"
                $root .= $path . '/';
                $path = '';
            } elseif ($path[2] === '/') {
                // Windows normal case: "C:/"..
                $root .= substr($path, 0, 3);
                $path = $length > 3 ? substr($path, 3) : '';
            }
        }

        return [$root, $path];
    }

    private function findCanonicalParts(string $root, string $pathWithoutRoot): array
    {
        $parts = explode('/', $pathWithoutRoot);

        $canonicalParts = [];

        // Collapse "." and "..", if possible
        foreach ($parts as $part) {
            if ($part === '.'
                || $part === ''
            ) {
                continue;
            }

            // Collapse ".." with the previous part, if one exists
            // Don't collapse ".." if the previous part is also ".."
            if ($part === '..'
                && \count($canonicalParts) > 0
                && $canonicalParts[\count($canonicalParts) - 1] !== '..'
            ) {
                array_pop($canonicalParts);
                continue;
            }

            // Only add ".." prefixes for relative paths
            if ($part !== '..'
                || $root === ''
            ) {
                $canonicalParts[] = $part;
            }
        }

        return $canonicalParts;
    }
}
