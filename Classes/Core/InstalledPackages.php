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

use Composer\InstalledVersions;
use Composer\Semver\VersionParser;

/**
 * Little helper service to gather information about the installed composer packages, which is a big befenit
 * for managing extension symlinking later on into the dedicated legacy test installation paths. Using the
 * available composer and cms-composer-installers runtime information makes this more robust and removes the
 * need for assumptions and guesses of installation structures.
 *
 * @internal This is a testing-framework internal service class and is not part of public testing-framework API.
 */
final class InstalledPackages
{
    public const TYPE_MONOREPO = 'monorepo';
    public const TYPE_EXTENSION = 'extension';
    public const TYPE_PROJECT = 'project';

    /**
     * @var string
     */
    private $packagesPath;

    /**
     * @var bool
     */
    private $composerInstallerFourOrHigherInstalled;

    /**
     * @var string
     */
    private $projectType = self::TYPE_PROJECT;

    /**
     * @var array
     */
    protected static $installedPackages = [];

    public function __construct()
    {
        // Let's calculate a proper path once to avoid wrong determination when symlinks has been invited to the party.
        $this->packagesPath = realpath(dirname($this->getPackageInstallPath('typo3/testing-framework'), 2));
        $this->composerInstallerFourOrHigherInstalled = InstalledVersions::satisfies(
            new VersionParser(),
            'typo3/cms-composer-installers',
            '4.0.0-RC1 || ^5'
        );
        if (!is_file($this->packagesPath . '/composer/installed.json')) {
            throw new \RuntimeException(
                '"vendor/composer/installed.json" file missing. Please require "typo3/cms-composer-installers" in '
                . 'proper version to the root composer.json.',
                1674932561
            );
        }

        $this->reload();
    }

    public function getPackagesPath(): string
    {
        return $this->packagesPath;
    }

    public function reload(): void
    {
        $typesMap = [
            'typo3-cms-core' => self::TYPE_MONOREPO,
            'typo3-cms-framework' => self::TYPE_EXTENSION,
            'typo3-cms-extension' => self::TYPE_EXTENSION,
            'project' => self::TYPE_PROJECT,
        ];
        $rootPackage = InstalledVersions::getRootPackage();
        $rootPackageName = $rootPackage['name'];
        $rootPackageType = $rootPackage['type'];
        $projectType = $typesMap[$rootPackageType] ?? '';
        if ($projectType === '') {
            throw new \RuntimeException(
                'Could not properly determine project type based on root composer.json package type.'
                . ' No match for "' . $rootPackageType . '" possible.'
            );
        }
        $this->projectType = $projectType;

        /**
         * @var array<string, array{pretty_version?: string, version?: string, reference?: string|null, extra?: array<string, mixed>, type?: string, install_path?: string, aliases?: string[], dev_requirement: bool, replaced?: string[], provided?: string[]}> $packages
         */
        $packages = [];
        $decoded = \json_decode(file_get_contents($this->packagesPath . '/composer/installed.json'), true);
        if (!is_array($decoded)) {
            throw new \Exception(
                'Could not decode "typo3/cms-composer-installers" installed.json file: '
                . $this->packagesPath . '/composer/installed.json',
                1674939842
            );
        }
        foreach ($decoded['packages'] ?? [] as $decodedPackage) {
            $packages[$decodedPackage['name']] = $decodedPackage;
        }
        // It seems that "typo3/cms-composer-installers" does not add the root package to the "installed.json"
        // file with enriched data. So we add it here now to have better lookup data at handy when we need it.
        if ($rootPackageType === 'typo3-cms-extension') {
            $packages[$rootPackageName] = $rootPackage;
            if (file_exists($this->getPackageInstallPath($rootPackageName) . '/composer.json')) {
                $packages[$rootPackageName] = array_replace(
                    json_decode(file_get_contents($this->getPackageInstallPath($rootPackageName) . '/composer.json'), true) ?? [],
                    $rootPackage
                );
            }
        }

        $packagesPerType = [];
        $packagesExtensionKeyToNameMap = [];
        foreach (InstalledVersions::getAllRawData() as $loader) {
            foreach ($loader['versions'] as $packageName => $packageInfo) {
                /**
                 * @var array{pretty_version?: string, version?: string, reference?: string|null, extra?: array<string, mixed>, type?: string, install_path?: string, aliases?: string[], dev_requirement: bool, replaced?: string[], provided?: string[]} $package
                 */
                $package = array_replace(
                    $packageInfo,
                    $packages[$packageName] ?? []
                );
                $packageType = $package['type'] ?? '';
                $packageExtensionKey = (string)($package['extra']['typo3/cms']['extension-key'] ?? '');
                $packagesPerType[$packageType][] = $packageName;
                if ($packageExtensionKey !== '') {
                    $packagesExtensionKeyToNameMap[$packageExtensionKey] = $packageName;
                }
            }
        }
        $self = $this;
        array_walk($packages, static function (array &$package, string $packageName) use ($self) {
            // cleanup invalid install path key
            unset($package['install-path']);
            // normalize install path in package information
            $package['install_path'] = realpath($self->getPackageInstallPath($packageName));
        });
        self::$installedPackages = [
            'packages' => $packages,
            'typeMap' => $packagesPerType,
            'extensionKeyMap' => $packagesExtensionKeyToNameMap,
        ];
    }

    public function isCmsComposerInstallersFourOrHigher(): bool
    {
        return $this->composerInstallerFourOrHigherInstalled;
    }

    public function getPackageInstallPath(string $name): string
    {
        return rtrim(strtr(InstalledVersions::getInstallPath($name), '\\', '/'), '/');
    }

    public function getPackage(string $name): ?array
    {
        if ($this->getPackages()[$name] ?? false) {
            return $this->getPackages()[$name];
        }
        if (($this->getExtensionKeyMap()[$name] ?? false)
            && ($this->getPackages()[$this->getExtensionKeyMap()[$name]] ?? false)
        ) {
            return $this->getPackages()[$this->getExtensionKeyMap()[$name]];
        }
        return null;
    }

    public function getRealPackageName(string $name): string
    {
        if (str_starts_with($name, 'typo3conf/ext/')) {
            $name = basename($name);
        }
        if ($this->getExtensionKeyMap()[$name] ?? false) {
            return $this->getRealPackageName($this->getExtensionKeyMap()[$name]);
        }
        if ($this->getPackage($name) !== null) {
            return $name;
        }
        return '';
    }

    public function getPackagesPerType(string $type): array
    {
        $packages = [];
        foreach (self::$installedPackages['typeMap'][$type] ?? [] as $packageName) {
            $packages[$packageName] = $this->getPackage($packageName);
        }
        return $packages;
    }

    public function getPackages(): array
    {
        return self::$installedPackages['packages'] ?? [];
    }

    public function getProjectType(): string
    {
        return $this->projectType;
    }

    protected function getExtensionKeyMap(): array
    {
        return self::$installedPackages['extensionKeyMap'] ?? [];
    }
}
