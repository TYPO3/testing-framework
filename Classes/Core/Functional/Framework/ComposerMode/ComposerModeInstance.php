<?php

declare(strict_types=1);

namespace TYPO3\TestingFramework\Core\Functional\Framework\ComposerMode;

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
use TYPO3\CMS\Core\Package\Cache\PackageCacheInterface;
use TYPO3\TestingFramework\Composer\ComposerPackageManager;
use TYPO3\TestingFramework\Core\Exception;

/**
 * Builds composer mode test instances from a single, shared "superset" installation.
 *
 * The superset is a real composer installation containing every system extension and
 * every fixture extension, produced once per test run by
 * Build/Scripts/setupFunctionalComposerSuperset.php. It exists so that the package
 * artifact a test instance boots from is the artifact the real installer plugin built,
 * rather than one the testing framework invented.
 *
 * A test instance is *not* a copy of the superset. It is a small directory that borrows
 * the superset's vendor tree:
 *
 *   <instance>/vendor              -> <superset>/vendor
 *   <instance>/public/_assets      -> <superset>/public/_assets
 *   <instance>/config/system/…     own configuration
 *   <instance>/var, fileadmin, …   own state
 *
 * Package paths inside the artifact are relative to the project path, so the vendor
 * symlink is what makes them resolve. Classes are *not* loaded from the superset:
 * the root autoloader already maps every system extension and every fixture extension,
 * so registering a second autoloader - and with it a second copy of every third party
 * package - is unnecessary and avoided.
 *
 * Per test case, only the active package set differs. That is expressed by narrowing the
 * artifact's configuration, which the package manager treats as "these are active" while
 * still knowing about every installed package.
 *
 * @internal
 */
final class ComposerModeInstance
{
    /**
     * Raw artifact data, read from disk once per process.
     *
     * Deliberately the raw array and not the PackageCacheEntry built from it: see
     * getSupersetArtifact().
     */
    private static ?array $supersetArtifactData = null;

    /**
     * The artifact currently handed out, and the instance it was built for.
     */
    private static ?PackageCacheEntry $supersetArtifact = null;
    private static string $supersetArtifactInstancePath = '';

    /**
     * Absolute path of the shared superset installation.
     */
    public static function getSupersetPath(): string
    {
        $configured = (string)getenv('TYPO3_TESTING_SUPERSET_PATH');
        if ($configured !== '') {
            return rtrim($configured, '/');
        }
        if (!defined('ORIGINAL_ROOT')) {
            throw new Exception('ORIGINAL_ROOT is not defined, cannot locate the composer superset.', 1754092800);
        }
        return rtrim(ORIGINAL_ROOT, '/') . '/typo3temp/var/tests/composer-superset';
    }

    /**
     * Fails with an actionable message rather than a confusing bootstrap error when the
     * superset has not been built.
     */
    public static function assertSupersetIsBuilt(): void
    {
        $artifact = self::getSupersetPath() . '/vendor/typo3/PackageArtifact.php';
        if (!is_file($artifact)) {
            throw new Exception(
                sprintf(
                    'Composer mode functional tests need the shared composer installation, which was'
                    . ' not found at "%s". Build it with'
                    . ' Build/Scripts/setupFunctionalComposerSuperset.php, or run the suite through'
                    . ' runTests.sh, which builds it for you.',
                    self::getSupersetPath()
                ),
                1754092801
            );
        }
    }

    /**
     * Creates the instance directory. Everything shared with other instances is a symlink,
     * everything a test may write to is the instance's own.
     *
     * @param non-empty-string $instancePath
     * @param non-empty-string[] $additionalFoldersToCreate
     */
    public static function provisionInstanceDirectory(string $instancePath, array $additionalFoldersToCreate = []): void
    {
        $superset = self::getSupersetPath();
        // Plain mkdir rather than GeneralUtility::mkdir_deep: this runs before the
        // instance is bootstrapped, so the global configuration the latter reads its
        // permission masks from does not exist yet.
        // A composer installation splits the instance in two: everything web accessible
        // lives below public/, everything else beside it. fileadmin and typo3temp/assets
        // are web accessible, var/ and config/ are not - getting that wrong makes the
        // default file storage point at a directory that does not exist.
        foreach ([
            '/config/system',
            '/var/transient',
            '/var/log',
            '/public',
            '/public/fileadmin',
            '/public/typo3temp/assets',
            '/public/typo3temp/var/transient',
        ] as $directory) {
            self::createDirectory($instancePath . $directory);
        }
        foreach ($additionalFoldersToCreate as $directory) {
            self::createDirectory($instancePath . '/public/' . ltrim($directory, '/'));
        }
        self::symlink($superset . '/vendor', $instancePath . '/vendor');
        if (is_dir($superset . '/public/_assets')) {
            self::symlink($superset . '/public/_assets', $instancePath . '/public/_assets');
        }
    }

    /**
     * Name of the command line entry point written into a composer mode instance.
     */
    public const CLI_ENTRY_POINT = 'typo3-testing-cli.php';

    /**
     * Writes the command line entry point a composer mode instance is driven by.
     *
     * Tests that run a console command in a sub process cannot use EXT:core/bin/typo3 in
     * composer mode. That script derives its autoloader from its own __DIR__, and since
     * the instance borrows the superset's vendor tree - whose packages are themselves
     * symlinks into the core checkout - __DIR__ resolves all the way back to the core
     * checkout and the sub process boots with the root autoloader. TYPO3_COMPOSER_MODE is
     * then undefined, so the sub process runs in classic mode and scans for packages in a
     * directory the instance does not have.
     *
     * The generated script does in a sub process exactly what setUp() does in the test
     * process: force composer mode and boot with this instance's narrowed package set.
     *
     * @param non-empty-string $instancePath
     * @param non-empty-string[] $activeExtensionKeys
     */
    public static function writeCliEntryPoint(string $instancePath, array $activeExtensionKeys): void
    {
        $script = sprintf(
            <<<'PHP'
            <?php
            // Generated by the TYPO3 testing framework. Do not edit.
            declare(strict_types=1);
            $classLoader = require %s;
            // Baked in rather than inherited: a parent may spawn this through Symfony
            // Process, which builds the child environment from $_ENV and $_SERVER and
            // therefore drops putenv() values. Without these the layout is computed as
            // classic, no settings file is found, and the command runs against the
            // failsafe container.
            putenv('TYPO3_PATH_APP=' . %s);
            putenv('TYPO3_PATH_ROOT=' . %s);
            putenv('TYPO3_TESTING_SUPERSET_PATH=' . %s);
            $_SERVER['PWD'] = %s;
            $_SERVER['argv'][0] = 'index.php';
            \TYPO3\TestingFramework\Core\TestingBootstrapPackageCache::$packageCache =
                \TYPO3\TestingFramework\Core\Functional\Framework\ComposerMode\ComposerModeInstance::createPackageCache(
                    %s,
                    %s
                );
            \TYPO3\TestingFramework\Core\SystemEnvironmentBuilder::run(
                0,
                \TYPO3\CMS\Core\Core\SystemEnvironmentBuilder::REQUESTTYPE_CLI,
                true
            );
            exit(
                \TYPO3\TestingFramework\Core\TestingBootstrapRunner::init($classLoader, true)
                    ->get(\TYPO3\CMS\Core\Console\CommandApplication::class)
                    ->run()
            );
            PHP,
            var_export(self::getRootAutoloadPath(), true),
            var_export($instancePath, true),
            var_export($instancePath . '/public', true),
            var_export(self::getSupersetPath(), true),
            var_export($instancePath, true),
            var_export($instancePath, true),
            var_export($activeExtensionKeys, true)
        );
        file_put_contents($instancePath . '/' . self::CLI_ENTRY_POINT, $script . PHP_EOL);
    }

    /**
     * The autoloader the test process itself uses - the one that maps every system
     * extension and every fixture extension.
     */
    private static function getRootAutoloadPath(): string
    {
        return (new ComposerPackageManager())->getVendorPath() . '/autoload.php';
    }

    /**
     * The package cache a test instance boots from: every package of the superset stays
     * known, only the given ones are active.
     *
     * @param non-empty-string $instancePath
     * @param non-empty-string[] $activeExtensionKeys
     */
    public static function createPackageCache(string $instancePath, array $activeExtensionKeys): PackageCacheInterface
    {
        $full = self::getSupersetArtifact($instancePath);
        $configuration = $full->getConfiguration();
        $narrowed = ['version' => $configuration['version'] ?? 5, 'packages' => []];
        $missing = [];
        foreach ($activeExtensionKeys as $key) {
            if (!isset($configuration['packages'][$key])) {
                $missing[] = $key;
                continue;
            }
            $narrowed['packages'][$key] = $configuration['packages'][$key];
        }
        if ($missing !== []) {
            throw new Exception(
                sprintf(
                    'Extension(s) "%s" are not part of the composer superset installation. Every system'
                    . ' extension and every fixture extension below Tests/ is installed into it, so this'
                    . ' usually means the superset is stale - rebuild it with'
                    . ' Build/Scripts/setupFunctionalComposerSuperset.php.',
                    implode('", "', $missing)
                ),
                1754092802
            );
        }
        // The root package of the superset is registered as a package too, and TYPO3
        // expects the app package to be active.
        foreach (array_keys($configuration['packages']) as $key) {
            if (str_contains((string)$key, '/')) {
                $narrowed['packages'][$key] = $configuration['packages'][$key];
            }
        }

        return new InMemoryPackageCache(
            PackageCacheEntry::fromPackageData(
                $narrowed,
                $full->getAliasMap(),
                $full->getComposerNameMap(),
                $full->getPackages()
            )
        );
    }

    /**
     * The artifact stores package paths relative to the project path, and Package
     * resolves them against Environment::getProjectPath() on first access - once,
     * writing the absolute path back into the object. Package objects are therefore
     * bound to the instance that first touched them, and reusing one artifact across
     * instances makes every later instance look for its packages inside the first
     * instance's directory. Rebuild the entry whenever the instance changes; only the
     * raw artifact data is kept, and reading it from disk happens once per process.
     *
     * @param non-empty-string $instancePath
     */
    private static function getSupersetArtifact(string $instancePath): PackageCacheEntry
    {
        if (self::$supersetArtifact !== null && self::$supersetArtifactInstancePath === $instancePath) {
            return self::$supersetArtifact;
        }
        if (self::$supersetArtifactData === null) {
            self::assertSupersetIsBuilt();
            $data = require self::getSupersetPath() . '/vendor/typo3/PackageArtifact.php';
            if (!is_array($data)) {
                throw new Exception('The composer superset package artifact could not be read.', 1754092803);
            }
            self::$supersetArtifactData = $data;
        }
        self::$supersetArtifactInstancePath = $instancePath;

        return self::$supersetArtifact = PackageCacheEntry::fromCache(self::$supersetArtifactData);
    }

    private static function createDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }
        if (!@mkdir($path, 0o777, true) && !is_dir($path)) {
            throw new Exception(sprintf('Directory "%s" could not be created.', $path), 1754092805);
        }
    }

    private static function symlink(string $from, string $to): void
    {
        if (is_link($to) || file_exists($to)) {
            return;
        }
        if (!@symlink($from, $to)) {
            throw new Exception(sprintf('Creating link failed: from %s to %s', $from, $to), 1754092804);
        }
    }
}
