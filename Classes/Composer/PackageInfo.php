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
final class PackageInfo
{
    public function __construct(
        private readonly string $name,
        private readonly string $type,
        private readonly string $path,
        private readonly string $realPath,
        private readonly string $version,
        private readonly string $extensionKey,
        private readonly ?array $info = null,
        private readonly ?array $extEmConf = null,
    ) {
    }

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
        return (string)($this?->info['config']['vendor-dir'] ?? '');
    }
}
