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
 * Model of frontend response
 *
 * @deprecated Will be removed with core v12 compatible testing-framework
 */
class Response
{
    const STATUS_Failure = 'failure';

    /**
     * @var string
     */
    protected $status;

    /**
     * @var string|array|null
     */
    protected $content;

    /**
     * @var string
     */
    protected $error;

    /**
     * @var ResponseContent
     */
    protected $responseSection;

    /**
     * @param string $status
     * @param string $content
     * @param string $error
     */
    public function __construct($status, $content, $error)
    {
        $this->status = $status;
        $this->content = $content;
        $this->error = $error;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @return array|string|null
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * @return string
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * @return ResponseContent
     * @deprecated use ResponseContent::fromString() instead
     */
    public function getResponseContent()
    {
        if (!isset($this->responseContent)) {
            $this->responseContent = ResponseContent::fromString($this->getContent());
        }
        return $this->responseContent;
    }

    /**
     * @param mixed $sectionIdentifiers
     * @return array|ResponseSection[]|null
     * @deprecated use ResponseContent::getSections() instead
     */
    public function getResponseSections(...$sectionIdentifiers)
    {
        return $this->getResponseContent()->getSections(...$sectionIdentifiers);
    }
}
