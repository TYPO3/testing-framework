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
 * @deprecated Will be removed in v12 compatible testing-framework along with InternalResponse.
 */
class InternalResponseException extends \RuntimeException
{
    /**
     * @var string
     */
    private $type;

    /**
     * @param string $message
     * @param int $code
     * @param string $type
     */
    public function __construct(string $message, int $code, string $type)
    {
        parent::__construct($message, $code);
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }
}
