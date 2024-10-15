<?php

declare(strict_types=1);

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

namespace TYPO3\TestingFramework\Core;

use TYPO3\CMS\Core\Package\MetaData;
use TYPO3\CMS\Core\Package\MetaData\PackageConstraint;
use TYPO3\CMS\Core\Package\Package;
use TYPO3\CMS\Core\Package\PackageInterface;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Service\DependencyOrderingService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\TestingFramework\Composer\ComposerPackageManager;

/**
 * Collection for extension packages to resolve their dependencies in a test-base.
 * Most of the code has been duplicated and adjusted from `\TYPO3\CMS\Core\Package\PackageManager`.
 *
 * @phpstan-type PackageKey non-empty-string
 * @phpstan-type PackageName non-empty-string
 * @phpstan-type PackageConstraints array{dependencies: list<PackageKey>, suggestions: list<PackageKey>}
 * @phpstan-type StateConfiguration array{packagePath?: non-empty-string}
 *
 * @internal
 */
class PackageCollection
{
    /**
     * @var array<PackageKey, PackageInterface>
     */
    protected array $packages;

    /**
     * @param ComposerPackageManager $composerPackageManager
     * @param PackageManager $packageManager
     * @param array<PackageKey, StateConfiguration> $packageStates
     */
    public static function fromPackageStates(ComposerPackageManager $composerPackageManager, PackageManager $packageManager, string $basePath, array $packageStates): self
    {
        $packages = [];
        foreach ($packageStates as $packageKey => $packageStateConfiguration) {
            $packagePath = PathUtility::sanitizeTrailingSeparator(
                rtrim($basePath, '/') . '/' . $packageStateConfiguration['packagePath']
            );
            $packages[] = $package = new Package($packageManager, $packageKey, $packagePath);
            $packageManager->registerPackage($package);
        }

        return new self($composerPackageManager, ...$packages);
    }

    public function __construct(
        private ComposerPackageManager $composerPackageManager,
        PackageInterface ...$packages,
    ) {
        $this->packages = array_combine(
            array_map(static fn(PackageInterface $package) => $package->getPackageKey(), $packages),
            $packages
        );
    }

    /**
     * @return array<PackageKey, PackageInterface>
     */
    public function getPackages(): array
    {
        return $this->packages;
    }

    public function sortPackages(?DependencyOrderingService $dependencyOrderingService = null): void
    {
        $sortedPackageKeys = $this->resolveSortedPackageKeys($dependencyOrderingService);
        usort(
            $this->packages,
            static fn(PackageInterface $a, PackageInterface $b) =>
                array_search($a->getPackageKey(), $sortedPackageKeys, true)
                <=> array_search($b->getPackageKey(), $sortedPackageKeys, true)
        );
    }

    /**
     * @param array<PackageKey, StateConfiguration> $packageStates
     * @return array<PackageKey, StateConfiguration>
     */
    public function sortPackageStates(array $packageStates, ?DependencyOrderingService $dependencyOrderingService = null): array
    {
        $sortedPackageKeys = $this->resolveSortedPackageKeys($dependencyOrderingService);
        uksort(
            $packageStates,
            static fn(string $a, string $b) =>
                array_search($a, $sortedPackageKeys, true)
                <=> array_search($b, $sortedPackageKeys, true)
        );
        return $packageStates;
    }

    /**
     * Builds the dependency graph for all packages
     *
     * This method also introduces dependencies among the dependencies
     * to ensure the loading order is exactly as specified in the list.
     *
     * @return list<PackageKey>
     */
    public function resolveSortedPackageKeys(?DependencyOrderingService $dependencyOrderingService = null): array
    {
        $dependencyOrderingService ??= GeneralUtility::makeInstance(DependencyOrderingService::class);
        $allPackageConstraints = $this->resolveAllPackageConstraints();

        // sort the packages by key at first, so we get a stable sorting of "equivalent" packages afterwards
        ksort($allPackageConstraints);

        $frameworkKeys = $this->findFrameworkKeys();
        $frameworkDependencyGraph = $dependencyOrderingService->buildDependencyGraph(
            $this->convertConfigurationForGraph($allPackageConstraints, $frameworkKeys)
        );
        $rootKeys = $dependencyOrderingService->findRootIds($frameworkDependencyGraph);
        $allPackageConstraints = $this->addDependencyToFrameworkToAllExtensions($allPackageConstraints, $rootKeys, $frameworkKeys);

        $packageKeys = array_keys($allPackageConstraints);
        $dependencyGraph = $dependencyOrderingService->buildDependencyGraph(
            $this->convertConfigurationForGraph($allPackageConstraints, $packageKeys)
        );

        return $dependencyOrderingService->calculateOrder($dependencyGraph);
    }

    /**
     * Convert the package configuration into a dependency definition
     *
     * This converts "dependencies" and "suggestions" to "after" syntax for the usage in DependencyOrderingService
     */
    protected function convertConfigurationForGraph(array $allPackageConstraints, array $packageKeys): array
    {
        $dependencies = [];
        foreach ($packageKeys as $packageKey) {
            if (!isset($allPackageConstraints[$packageKey]['dependencies']) && !isset($allPackageConstraints[$packageKey]['suggestions'])) {
                continue;
            }
            $dependencies[$packageKey] = [
                'after' => [],
            ];
            if (isset($allPackageConstraints[$packageKey]['dependencies'])) {
                foreach ($allPackageConstraints[$packageKey]['dependencies'] as $dependentPackageKey) {
                    if (!in_array($dependentPackageKey, $packageKeys, true)) {
                        if ($this->isComposerDependency($dependentPackageKey)) {
                            // The given package has a dependency to a Composer package that has no relation to TYPO3
                            // We can ignore those, when calculating the extension order
                            continue;
                        }
                        throw new \UnexpectedValueException(
                            'The package "' . $packageKey . '" depends on "'
                            . $dependentPackageKey . '" which is not present in the system.',
                            1519931815
                        );
                    }
                    $dependencies[$packageKey]['after'][] = $dependentPackageKey;
                }
            }
            if (isset($allPackageConstraints[$packageKey]['suggestions'])) {
                foreach ($allPackageConstraints[$packageKey]['suggestions'] as $suggestedPackageKey) {
                    // skip suggestions on not existing packages
                    if (in_array($suggestedPackageKey, $packageKeys, true)) {
                        // Suggestions actually have never been meant to influence loading order.
                        // We misuse this currently, as there is no other way to influence the loading order
                        // for not-required packages (soft-dependency).
                        // When considering suggestions for the loading order, we might create a cyclic dependency
                        // if the suggested package already has a real dependency on this package, so the suggestion
                        // has do be dropped in this case and must *not* be taken into account for loading order evaluation.
                        $dependencies[$packageKey]['after-resilient'][] = $suggestedPackageKey;
                    }
                }
            }
        }
        return $dependencies;
    }

    /**
     * Adds all root packages of current dependency graph as dependency to all extensions
     *
     * This ensures that the framework extensions (aka sysext) are
     * always loaded first, before any other external extension.
     *
     * @param array<PackageKey, PackageConstraints> $allPackageConstraints
     * @param list<PackageKey> $rootPackageKeys
     * @return array<PackageKey, PackageConstraints>
     */
    protected function addDependencyToFrameworkToAllExtensions(array $allPackageConstraints, array $rootPackageKeys, array $frameworkKeys): array
    {
        $extensionPackageKeys = array_diff(array_keys($allPackageConstraints), $frameworkKeys);
        foreach ($extensionPackageKeys as $packageKey) {
            // Remove framework packages from list
            $packageKeysWithoutFramework = array_diff(
                $allPackageConstraints[$packageKey]['dependencies'],
                $frameworkKeys
            );
            // The order of the array_merge is crucial here,
            // we want the framework first
            $allPackageConstraints[$packageKey]['dependencies'] = array_merge(
                $rootPackageKeys,
                $packageKeysWithoutFramework
            );
        }
        return $allPackageConstraints;
    }

    protected function resolveAllPackageConstraints(): array
    {
        $dependencies = [];
        foreach ($this->packages as $package) {
            $packageKey = $package->getPackageKey();
            $dependencies[$packageKey]['dependencies'] = $this->getDependencyArrayForPackage($package);
            $dependencies[$packageKey]['suggestions'] = $this->getSuggestionArrayForPackage($package);
        }
        return $dependencies;
    }

    /**
     * Returns an array of dependent package keys for the given package. It will
     * do this recursively, so dependencies of dependent packages will also be
     * in the result.
     *
     * @param list<PackageInterface> $trace An array of already visited packages, to detect circular dependencies
     * @return list<PackageKey> An array of direct or indirect dependent packages
     * @throws Exception
     */
    protected function getDependencyArrayForPackage(PackageInterface $package, array &$dependentPackageKeys = [], array $trace = []): array
    {
        if (in_array($package, $trace, true)) {
            return $dependentPackageKeys;
        }
        $trace[] = $package;
        $dependentPackageConstraints = $package->getPackageMetaData()
            ->getConstraintsByType(MetaData::CONSTRAINT_TYPE_DEPENDS);
        foreach ($dependentPackageConstraints as $constraint) {
            if ($constraint instanceof PackageConstraint) {
                $dependentPackageKey = $constraint->getValue();
                $extensionKey = $this->composerPackageManager->getPackageInfo($dependentPackageKey)?->getExtensionKey() ?? '';
                if (!in_array($dependentPackageKey, $dependentPackageKeys, true) && !in_array($dependentPackageKey, $trace, true)) {
                    $dependentPackageKeys[] = $extensionKey ?: $dependentPackageKey;
                }

                if (!isset($this->packages[$dependentPackageKey]) && !isset($this->packages[$extensionKey])) {
                    if ($this->isComposerDependency($dependentPackageKey)) {
                        // The given package has a dependency to a Composer package that has no relation to TYPO3
                        // We can ignore those, when calculating the extension order
                        continue;
                    }

                    throw new Exception(
                        sprintf(
                            'Package "%s" depends on package "%s" which does not exist.',
                            $package->getPackageKey(),
                            $dependentPackageKey
                        ),
                        1695119749
                    );
                }
                $this->getDependencyArrayForPackage($this->packages[$dependentPackageKey] ?? $this->packages[$extensionKey], $dependentPackageKeys, $trace);
            }
        }
        return array_reverse($dependentPackageKeys);
    }

    /**
     * Returns an array of suggested package keys for the given package.
     *
     * @return list<PackageKey> An array of directly suggested packages
     */
    protected function getSuggestionArrayForPackage(PackageInterface $package): array
    {
        $suggestedPackageKeys = [];
        $suggestedPackageConstraints = $package->getPackageMetaData()
            ->getConstraintsByType(MetaData::CONSTRAINT_TYPE_SUGGESTS);
        foreach ($suggestedPackageConstraints as $constraint) {
            if ($constraint instanceof PackageConstraint) {
                $suggestedPackageKey = $constraint->getValue();
                if (isset($this->packages[$suggestedPackageKey])) {
                    $suggestedPackageKeys[] = $suggestedPackageKey;
                }
            }
        }
        return array_reverse($suggestedPackageKeys);
    }

    /**
     * @return list<PackageKey>
     */
    protected function findFrameworkKeys(): array
    {
        $frameworkKeys = [];
        foreach ($this->packages as $package) {
            if ($package->getPackageMetaData()->isFrameworkType()) {
                $frameworkKeys[] = $package->getPackageKey();
            }
        }
        return $frameworkKeys;
    }

    protected function isComposerDependency(string $packageKey): bool
    {
        $packageInfo = $this->composerPackageManager->getPackageInfo($packageKey);
        return !(($packageInfo?->isSystemExtension() ?? false) || ($packageInfo?->isExtension()));
    }
}
