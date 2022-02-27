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
use TYPO3\CMS\Core\Utility\Exception\MissingArrayPathException;

/**
 * Model of frontend response content
 */
class ResponseContent
{
    /**
     * @var array|ResponseSection[]
     */
    protected $sections;

    /**
     * @var array
     */
    protected $structure;

    /**
     * @var array
     */
    protected $records;

    /**
     * @var array
     */
    protected $scope = [];

    public static function fromString(string $data, ResponseContent $target = null): ResponseContent
    {
        $target = $target ?? new static();
        $content = json_decode($data, true);

        if ($content !== null && is_array($content)) {
            foreach ($content as $sectionIdentifier => $sectionData) {
                try {
                    $section = new ResponseSection($sectionIdentifier, $sectionData);
                    $target->sections[$sectionIdentifier] = $section;
                } catch (\RuntimeException $exception) {
                }
            }
            $target->scope = $content['Scope'] ?? [];
        }

        return $target;
    }

    /**
     * @param Response $response (deprecated)
     */
    public function __construct(Response $response = null)
    {
        if ($response instanceof Response) {
            static::fromString($response->getContent(), $this);
        }
    }

    /**
     * @param string $sectionIdentifier
     * @return ResponseSection|null
     * @throws \RuntimeException
     */
    public function getSection($sectionIdentifier)
    {
        if (isset($this->sections[$sectionIdentifier])) {
            return $this->sections[$sectionIdentifier];
        }

        throw new \RuntimeException('ResponseSection "' . $sectionIdentifier . '" does not exist', 1476122151);
    }

    /**
     * @param string ...$sectionIdentifiers
     * @return ResponseSection[]
     */
    public function getSections(string ...$sectionIdentifiers): array
    {
        if (empty($sectionIdentifiers)) {
            $sectionIdentifiers = ['Default'];
        }

        return array_map(
            function (string $sectionIdentifier) {
                return $this->getSection($sectionIdentifier);
            },
            $sectionIdentifiers
        );
    }

    /**
     * @param string $path
     * @return mixed|null
     */
    public function getScopePath(string $path)
    {
        try {
            return ArrayUtility::getValueByPath($this->scope, $path, '/');
        } catch (MissingArrayPathException $exception) {
            return null;
        }
    }
}
