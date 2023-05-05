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
    private string $name;
    private string $type;
    private string $path;
    private string $realPath;
    private string $version;
    private string $extensionKey;
    private ?array $info = null;
    private ?array $extEmConf = null;

    public function __construct(
        string $name,
        string $type,
        string $path,
        string $realPath,
        string $version,
        string $extensionKey,
        ?array $info = null,
        ?array $extEmConf = null
    ) {
        $this->name = $name;
        $this->type = $type;
        $this->path = $path;
        $this->realPath = $realPath;
        $this->version = $version;
        $this->extensionKey = $extensionKey;
        $this->info = $info;
        $this->extEmConf = $extEmConf;
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
        return (string)($this->info['config']['vendor-dir'] ?? '');
    }
}
