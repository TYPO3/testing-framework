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
 * @internal This class is for testing-framework internal processing and not part of public testing API.
 */
final readonly class PackageInfo
{
    public function __construct(
        private string $name,
        private string $type,
        private string $path,
        private string $realPath,
        private string $version,
        private string $extensionKey,
        private ?array $info = null,
        private ?array $extEmConf = null,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getRealPath(): string
    {
        return $this->realPath;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getInfo(): ?array
    {
        return $this->info;
    }

    public function getExtEmConf(): ?array
    {
        return $this->extEmConf;
    }

    public function isSystemExtension(): bool
    {
        return $this->type === 'typo3-cms-framework';
    }

    public function isExtension(): bool
    {
        return $this->type === 'typo3-cms-extension';
    }

    public function isMonoRepository(): bool
    {
        return $this->type === 'typo3-cms-core';
    }

    public function isComposerPackage(): bool
    {
        return $this->info !== null;
    }

    public function getExtensionKey(): string
    {
        return $this->extensionKey;
    }

    public function getVendorDir(): string
    {
        return (string)($this->info['config']['vendor-dir'] ?? '');
    }

    public function getWebDir(): string
    {
        return (string)($this->info['extra']['typo3/cms']['web-dir'] ?? '');
    }

    /**
     * @return string[]
     */
    public function getReplacesPackageNames(): array
    {
        $keys = array_keys($this->info['replace'] ?? []);
        if ($this->isMonoRepository()) {
            // TYPO3 monorepo root package (composer.json) replaces `typo3/sysext/*` packages
            // ending up with the root package composer information being used in composer
            // `InstalledVersions` for the original package names. We need the correct package
            // information and is the reason why we remove these replace package names to allow
            // adding `typo3/sysext/*` packages in `ComposerPackageManager->processMonoRepository()`.
            // Dropping is only made for extension package names starting with `typo3/cms-` or
            // `typo3/theme-`.
            $keys = array_filter($keys, static fn($value) => !(str_starts_with($value, 'typo3/cms-') || str_starts_with($value, 'typo3/theme-')));
        }
        return $keys;
    }
}
