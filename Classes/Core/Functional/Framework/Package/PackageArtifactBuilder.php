<?php
declare(strict_types=1);

namespace TYPO3\TestingFramework\Core\Functional\Framework\Package;

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
use TYPO3\CMS\Core\Package\Package;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Service\DependencyOrderingService;
use TYPO3\TestingFramework\Core\Exception;

/**
 * Very basic artifact builder, which does not take ordering by dependency into account at all
 */
class PackageArtifactBuilder
{
    /**
     * @var string
     */
    private $instancePath;

    public function __construct(string $instancePath)
    {
        $this->instancePath = $instancePath;
    }

    public function writePackageArtifact($packageStatesConfiguration): void
    {
        $packageManager = new PackageManager(new DependencyOrderingService(), '', '');
        $composerNameToPackageKeyMap = [];
        $packageAliasMap = [];
        $packages = [];

        foreach ($packageStatesConfiguration['packages'] as $extensionKey => $stateConfig) {
            $packagePath = $this->instancePath . '/' . $stateConfig['packagePath'];
            $package = new Package($packageManager, $extensionKey, $packagePath);
            $composerNameToPackageKeyMap[$package->getValueFromComposerManifest('name')] = $extensionKey;
            $packages[$extensionKey] = $package;
            foreach ($package->getPackageReplacementKeys() as $packageToReplace => $versionConstraint) {
                $packageAliasMap[$packageToReplace] = $extensionKey;
            }
        }

        $cacheEntry = PackageCacheEntry::fromPackageData(
            $packageStatesConfiguration,
            $packageAliasMap,
            $composerNameToPackageKeyMap,
            $packages
        )->withIdentifier(md5('typo3-testing-' . $this->instancePath));

        $buildDir = $this->instancePath . '/typo3temp/var/build';
        $this->ensureDirectoryExists($buildDir);

        $result = file_put_contents(
            $buildDir . '/PackageArtifact.php',
            '<?php' . PHP_EOL . 'return ' . PHP_EOL . $cacheEntry->serialize() . ';'
        );

        if (!$result) {
            throw new Exception('Can not write PackageArtifact', 1630268883);
        }
    }

    private function ensureDirectoryExists(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }
}
