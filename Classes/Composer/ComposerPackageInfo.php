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

/**
 * PARENT PROPERTIES:
 * private static $installed;
 * private static $canGetVendors;
 * private static $installedByVendor = array();
 *
 * PARENT METHODS:
 * public static function getInstalledPackages()
 * public static function getInstalledPackagesByType($type)
 * public static function isInstalled($packageName, $includeDevRequirements = true)
 * public static function satisfies(VersionParser $parser, $packageName, $constraint)
 * public static function getVersionRanges($packageName)
 * public static function getVersion($packageName)
 * public static function getPrettyVersion($packageName)
 * public static function getReference($packageName)
 * public static function getInstallPath($packageName)
 * public static function getRootPackage()
 * public static function getRawData() @deprecated
 * public static function getAllRawData()
 * public static function reload($data)
 * private static function getInstalled()
 */

/**
 * TYPO3 related extending class of \Composer\InstalledVersions.
 * @see https://github.com/composer/composer/blob/main/src/Composer/InstalledVersions.php
 */
class ComposerPackageInfo extends \Composer\InstalledVersions
{
    /**
     * Get's all TYPO3 local extensions that are installed.
     * This is ignoring system extensions.
     */
    public static function getLocalExtensions()
    {
        return self::getInstalledPackagesByType('typo3-cms-extension');
    }

    /**
     * Get's all TYPO3 system extensions that are installed.
     * This is ignoring local extensions.
     */
    public static function getSystemExtensions()
    {
        return self::getInstalledPackagesByType('typo3-cms-framework');
    }

    /**
     * Get's all TYPO3 extensions that are installed.
     * This is ignoring i.e. packages that serve as composer plugin or are independent libraries
     */
    public static function getAllExtensions()
    {
        $localExtensions = self::getLocalExtensions();
        $systemExtensions = self::getSystemExtensions();
        return array_merge($systemExtensions, $localExtensions);
    }

    /**
     * Get's all TYPO3 local extensions that are installed.
     * This is ignoring system extensions.
     */
    public static function getLocalExtensionsEnriched()
    {
        $localExtensions = self::getLocalExtensions();
        $localExtensionsEnriched = self::getExtensionsEnriched($localExtensions);
        return $localExtensionsEnriched;
    }

    /**
     * Get's all TYPO3 system extensions that are installed.
     * This is ignoring local extensions.
     */
    public static function getSystemExtensionsEnriched()
    {
        $systemExtensions = self::getSystemExtensions();
        $systemExtensionsEnriched = self::getExtensionsEnriched($systemExtensions);
        return $systemExtensionsEnriched;
    }

    public static function getExtensionsEnriched($composerNameArray)
    {
        if (!is_array($composerNameArray)) {
            return false;
        }
        $extensionsEnriched = [];
        foreach ($composerNameArray as $count => $composerName) {
            $extensionDetails = self::getExtensionDetails($composerName);
            $extensionsEnriched[$composerName] = $extensionDetails;
            $extensionsEnriched[$composerName]['composerName'] = $composerName;
            if (!empty($extensionDetails['install_path'])) {
                $extensionsEnriched[$composerName]['extensionKey'] = self::getExtensionKey($composerName, $extensionDetails['install_path']);
            }
        }
        return $extensionsEnriched;
    }

    /**
     * Get's all TYPO3 extensions that are installed.
     * This is ignoring i.e. packages that serve as composer plugin or are independent libraries
     */
    public static function getAllExtensionsEnriched()
    {
        $localExtensionsEnriched = self::getLocalExtensionsEnriched();
        $systemExtensionsEnriched = self::getSystemExtensionsEnriched();
        return array_merge_recursive($localExtensionsEnriched, $systemExtensionsEnriched);
    }

    public static function getExtensionDetails($composerName)
    {
        $extensionDetails = [];
        $allRawData = self::getAllRawData($composerName);
        if (array_key_exists($composerName, $allRawData[0]['versions'])) {
            $extensionDetails = $allRawData[0]['versions'][$composerName];
        }
        elseif ($composerName == $allRawData[0]['root']['name']) {
            $extensionDetails = null;
        }
        else {
            $extensionDetails = false;
        }
        return $extensionDetails;
    }

    public static function getExtensionKey($composerName, $installPath)
    {
        $data = self::getJsonConfiguration($composerName, $installPath);
        $extensionKey = $data['extra']['typo3/cms']['extension-key'] ?? '';
        return $extensionKey;
    }

    /**
     * Gets the full configuration of an extension's composer.json.
     * Can be used to get any details which are not provided by composer API by default.
     * Used for getExtensionKey()
     *
     * @param string
     * @param string
     *
     * @return mixed [array | false]
     */
    public static function getJsonConfiguration($composerName, $installPath)
    {
        $filePath = $installPath . '/composer.json';
        if (!is_file($filePath)) {
            return false;
        }
        $content = file_get_contents($filePath);
        $data = json_decode($content, true);
        return $data;
    }

    public static function resolveExtensionPath($extensionPath)
    {
        if (strpos($extensionPath, 'EXT:') === 0) {
            $extKey = substr($extensionPath, 4);
            $allConfig = self::getAllExtensionsEnriched();
            foreach ($allConfig as $count => $extConf) {
                if ($extConf['extensionKey'] == $extKey) {
                    return $extConf['install_path'];
                }
            }
            return false;
        }
        return $extensionPath;
    }

    public static function getVendorPath()
    {
        // return realpath(InstalledVersions::getInstallPath('typo3/cms-core') . '/../../');
        return dirname($_composer_autoload_path);
    }
}
