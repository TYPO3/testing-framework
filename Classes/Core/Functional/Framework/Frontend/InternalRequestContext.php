<?php

declare(strict_types=1);

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
 * Helper class to run frontend functional tests with a logged in frontend
 * or backend user, in a workspaces.
 *
 * An instance of this class can be hand over as second argument to
 * executeFrontendSubRequest(), after calling withFrontendUserId() or
 * withBackendUserId() and withWorkspaceId() on it.
 *
 * The testing-framework ext:json_response extension middlewares act
 * on this and creates sessions and state accordingly.
 *
 * Matching fe / be / workspace must exist in the database, the test
 * setup needs to take care of that.
 */
class InternalRequestContext
{
    private ?int $frontendUserId = null;
    private ?int $backendUserId = null;
    private ?int $workspaceId = null;

    public function getFrontendUserId(): ?int
    {
        return $this->frontendUserId;
    }

    public function getBackendUserId(): ?int
    {
        return $this->backendUserId;
    }

    public function getWorkspaceId(): ?int
    {
        return $this->workspaceId;
    }

    public function withFrontendUserId(int $frontendUserId): InternalRequestContext
    {
        $target = clone $this;
        $target->frontendUserId = $frontendUserId;
        return $target;
    }

    public function withBackendUserId(int $backendUserId): InternalRequestContext
    {
        $target = clone $this;
        $target->backendUserId = $backendUserId;
        return $target;
    }

    public function withWorkspaceId(int $workspaceId): InternalRequestContext
    {
        $target = clone $this;
        $target->workspaceId = $workspaceId;
        return $target;
    }
}
