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

use TYPO3\CMS\Core\Http\Response;
use TYPO3\TestingFramework\Core\Functional\Framework\AssignablePropertyTrait;

/**
 * Model of internal frontend response.
 *
 * @deprecated Obsolete layer. Do not type hint this class. v12 core compatible testing-framework will return PSR-7 ResponseInterface
 */
class InternalResponse extends Response
{
    use AssignablePropertyTrait;

    /**
     * @param array $data
     * @return InternalResponse
     */
    public static function fromArray(array $data): InternalResponse
    {
        $target = new static(
            $data['body'] ?? '',
            $data['statusCode'] ?? 0,
            $data['headers'] ?? []
        );
        unset($data['body'], $data['statusCode'], $data['headers']);
        return $target->assign($data);
    }

    /**
     * @param string $body
     * @param int $statusCode
     * @param array $headers
     */
    public function __construct(string $body, int $statusCode = 200, array $headers = [])
    {
        parent::__construct('php://temp', $statusCode, $headers);
        $this->getBody()->write($body);
    }
}
