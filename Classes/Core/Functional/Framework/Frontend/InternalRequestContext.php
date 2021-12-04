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

/**
 * Model of internal frontend request context.
 *
 * Helper class for frontend requests to hand over details like 'a backend user should be logged in'.
 * This is used by testing-framework extension ext:json_response in its middlewares.
 */
class InternalRequestContext
{
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
     * @param int $frontendUserId
     * @return InternalRequestContext
     */
    public function withFrontendUserId(int $frontendUserId): InternalRequestContext
    {
        $target = clone $this;
        $target->frontendUserId = $frontendUserId;
        return $target;
    }

    /**
     * @param int $backendUserId
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
}
