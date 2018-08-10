<?php
namespace TYPO3\TestingFramework\Core\Functional\Framework\Frontend;

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

use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\AssignablePropertyTrait;

/**
 * Model of internal frontend request context
 */
class InternalRequestContext implements \JsonSerializable
{
    use AssignablePropertyTrait;

    /**
     * @var int
     */
    private $frontendUserId;

    /**
     * @var int
     */
    private $backendUserId;

    /**
     * @var int
     */
    private $workspaceId;

    /**
     * @var array
     */
    private $globalSettings;

    /**
     * @param array $data
     * @return InternalRequestContext
     */
    public static function fromArray(array $data)
    {
        return (new static())->with($data);
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }

    /**
     * @return null|int
     */
    public function getFrontendUserId(): ?int
    {
        return $this->frontendUserId;
    }

    /**
     * @return null|int
     */
    public function getBackendUserId(): ?int
    {
        return $this->backendUserId;
    }

    /**
     * @return null|int
     */
    public function getWorkspaceId(): ?int
    {
        return $this->workspaceId;
    }

    /**
     * @return null|array
     */
    public function getGlobalSettings(): ?array
    {
        return $this->globalSettings;
    }

    /**
     * @param int $workspaceId
     * @return InternalRequestContext
     */
    public function withFrontendUserId(int $frontendUserId): InternalRequestContext
    {
        $target = clone $this;
        $target->frontendUserId = $frontendUserId;
        return $target;
    }

    /**
     * @param int $workspaceId
     * @return InternalRequestContext
     */
    public function withBackendUserId(int $backendUserId): InternalRequestContext
    {
        $target = clone $this;
        $target->backendUserId = $backendUserId;
        return $target;
    }

    /**
     * @param int $workspaceId
     * @return InternalRequestContext
     */
    public function withWorkspaceId(int $workspaceId): InternalRequestContext
    {
        $target = clone $this;
        $target->workspaceId = $workspaceId;
        return $target;
    }

    /**
     * @param array $globalSettings
     * @return InternalRequestContext
     */
    public function withGlobalSettings(array $globalSettings): InternalRequestContext
    {
        if (empty($globalSettings)) {
            return $this;
        }
        $target = clone $this;
        $target->globalSettings = $globalSettings;
        return $target;
    }

    /**
     * @param array $globalSettings
     * @return InternalRequestContext
     */
    public function withMergedGlobalSettings(array $globalSettings): InternalRequestContext
    {
        if (empty($globalSettings)) {
            return $this;
        }
        $target = clone $this;
        $target->globalSettings = $this->globalSettings ?? [];
        ArrayUtility::mergeRecursiveWithOverrule(
            $target->globalSettings,
            $globalSettings
        );
        return $target;
    }
}
