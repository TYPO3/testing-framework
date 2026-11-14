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

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Package\Cache\PackageCacheEntry;
use TYPO3\TestingFramework\Composer\ComposerPackageManager;
use TYPO3\TestingFramework\Core\Exception;

/**
 * Builds composer mode test instances from a single, shared composer installation.
 *
 * Composer mode is opt-in with TYPO3_TESTING_INSTANCE_MODE=composer. Without it, every
 * test instance is a classic mode instance, exactly as before.
 *
 * The shared installation is a real composer installation containing every extension
 * any test case activates. TYPO3 Core builds it with
 * Build/Scripts/setupFunctionalComposerSuperset.php. It is looked up in
 * typo3temp/var/tests/composer-superset below the original root, or wherever
 * TYPO3_TESTING_SUPERSET_PATH points to. The package artifact a test instance boots
 * from is therefore derived from the one the real installer plugin built, not one the
 * testing framework invented.
 *
 * A test instance is *not* a copy of the shared installation. It is a small directory
 * that borrows its vendor tree:
 *
 *   <instance>/vendor              -> <shared>/vendor
 *   <instance>/public/_assets      -> <shared>/public/_assets
 *   <instance>/config/system/…     own configuration
 *   <instance>/var, public/…       own state
 *
 * Package paths inside the artifact are relative to the project path, so the vendor
 * symlink is what makes them resolve. Classes are *not* loaded from the shared
 * installation: the root autoloader already maps every extension, so registering a
 * second autoloader - and with it a second copy of every third party package - is
 * unnecessary and avoided.
 *
 * Per test case, only the active package set differs. It is written into the instance
 * as its own package artifact, the composer mode counterpart of the PackageStates.php
 * of a classic mode instance.
 *
 * @internal
 */
final class ComposerModeInstance
{
    /**
     * Name of the command line entry point written into a composer mode instance.
     */
    public const CLI_ENTRY_POINT = 'typo3-testing-cli.php';

    private const PACKAGE_ARTIFACT_DIRECTORY = 'var/testing-framework';
    private const PACKAGE_ARTIFACT_FILE = 'PackageArtifact.php';

    /**
     * Whether test instances are provisioned in composer mode.
     */
    public static function isEnabled(): bool
    {
        $mode = (string)getenv('TYPO3_TESTING_INSTANCE_MODE');
        if ($mode === '' || $mode === 'classic') {
            return false;
        }
        if ($mode !== 'composer') {
            throw new Exception(
                sprintf('TYPO3_TESTING_INSTANCE_MODE must be "classic" or "composer", "%s" given.', $mode),
                1794653447
            );
        }
        // TestingBootstrapRunner extends Bootstrap, which is a readonly class since TYPO3
        // v14.3 only. Loading it on an older version is a fatal error rather than a message.
        $typo3Version = new Typo3Version();
        if (version_compare($typo3Version->getBranch(), '14.3', '<')) {
            throw new Exception(
                sprintf('Composer mode functional tests need TYPO3 v14.3 or later, %s is installed.', $typo3Version->getVersion()),
                1794653448
            );
        }

        return true;
    }

    /**
     * Absolute path of the shared composer installation.
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
     * Directory holding the package artifact of a composer mode instance, in the form
     * TYPO3's ComposerPackageArtifact expects it.
     *
     * @param non-empty-string $instancePath
     */
    public static function getPackageArtifactDirectory(string $instancePath): string
    {
        return $instancePath . '/' . self::PACKAGE_ARTIFACT_DIRECTORY;
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
        self::assertSupersetIsBuilt();
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
     * Writes the package artifact of a test instance: every package of the shared
     * installation stays known, only the given ones are active.
     *
     * The artifact is written once, when the instance is created, and read by every
     * bootstrap of the instance - in the test process and in console sub processes alike.
     * Each bootstrap unserializes fresh package objects from it. That matters: a package
     * resolves its relative path against the project path of whichever instance first
     * asks for it and keeps the result, so package objects must never be shared between
     * instances.
     *
     * @param non-empty-string $instancePath
     * @param non-empty-string[] $activeExtensionKeys
     */
    public static function writePackageArtifact(string $instancePath, array $activeExtensionKeys): void
    {
        self::assertSupersetIsBuilt();
        $data = require self::getSupersetArtifactFile();
        if (!is_array($data)) {
            throw new Exception('The composer superset package artifact could not be read.', 1754092803);
        }
        $full = PackageCacheEntry::fromCache($data);
        $configuration = $full->getConfiguration();
        $missing = array_diff($activeExtensionKeys, array_keys($configuration['packages']));
        if ($missing !== []) {
            throw new Exception(
                sprintf(
                    'Extension(s) "%s" are not part of the shared composer installation at "%s". It has to'
                    . ' contain every extension a test case loads, so this usually means it is stale.'
                    . ' TYPO3 Core rebuilds it with Build/Scripts/setupFunctionalComposerSuperset.php.',
                    implode('", "', $missing),
                    self::getSupersetPath()
                ),
                1754092802
            );
        }
        // Keep the order of the shared artifact, which the installer sorted by dependencies.
        // The root package of the shared installation is registered as a package too, and
        // TYPO3 expects the app package to be active.
        $narrowed = ['version' => $configuration['version'] ?? 5, 'packages' => []];
        foreach ($configuration['packages'] as $key => $packageConfiguration) {
            if (in_array($key, $activeExtensionKeys, true) || str_contains((string)$key, '/')) {
                $narrowed['packages'][$key] = $packageConfiguration;
            }
        }
        $identifier = sha1(($full->getIdentifier() ?? '') . '|' . implode(',', array_keys($narrowed['packages'])));
        $entry = PackageCacheEntry::fromPackageData(
            $narrowed,
            $full->getAliasMap(),
            $full->getComposerNameMap(),
            $full->getPackages()
        )->withIdentifier($identifier);

        $directory = self::getPackageArtifactDirectory($instancePath);
        self::createDirectory($directory);
        $result = @file_put_contents(
            $directory . '/' . self::PACKAGE_ARTIFACT_FILE,
            '<?php' . PHP_EOL . 'return ' . PHP_EOL . $entry->serialize() . ';' . PHP_EOL
        );
        if ($result === false) {
            throw new Exception('Can not write the package artifact of the test instance.', 1794653449);
        }
    }

    /**
     * Writes the command line entry point a composer mode instance is driven by.
     *
     * Tests that run a console command in a sub process cannot use EXT:core/bin/typo3 in
     * composer mode. That script derives its autoloader from its own __DIR__, and since
     * the instance borrows the shared vendor tree - whose packages may themselves be
     * symlinks to elsewhere - __DIR__ resolves away from the instance and the sub process
     * boots with whatever autoloader it finds there. TYPO3_COMPOSER_MODE is then
     * undefined, so the sub process runs in classic mode and scans for packages in a
     * directory the instance does not have.
     *
     * The generated script does in a sub process exactly what setUp() does in the test
     * process: force composer mode and boot from this instance's package artifact.
     *
     * @param non-empty-string $instancePath
     */
    public static function writeCliEntryPoint(string $instancePath): void
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
            putenv('TYPO3_TESTING_INSTANCE_MODE=composer');
            $_SERVER['PWD'] = %s;
            $_SERVER['argv'][0] = 'index.php';
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
            var_export((new ComposerPackageManager())->getVendorPath() . '/autoload.php', true),
            var_export($instancePath, true),
            var_export($instancePath . '/public', true),
            var_export($instancePath, true)
        );
        file_put_contents($instancePath . '/' . self::CLI_ENTRY_POINT, $script . PHP_EOL);
    }

    /**
     * Fails with an actionable message rather than a confusing bootstrap error when the
     * shared installation has not been built.
     */
    private static function assertSupersetIsBuilt(): void
    {
        if (!is_file(self::getSupersetArtifactFile())) {
            throw new Exception(
                sprintf(
                    'Composer mode functional tests need a shared composer installation, which was'
                    . ' not found at "%s". Set TYPO3_TESTING_SUPERSET_PATH to point to one. TYPO3 Core'
                    . ' builds it with Build/Scripts/setupFunctionalComposerSuperset.php, and runTests.sh'
                    . ' does that for you.',
                    self::getSupersetPath()
                ),
                1754092801
            );
        }
    }

    private static function getSupersetArtifactFile(): string
    {
        return self::getSupersetPath() . '/vendor/typo3/' . self::PACKAGE_ARTIFACT_FILE;
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
