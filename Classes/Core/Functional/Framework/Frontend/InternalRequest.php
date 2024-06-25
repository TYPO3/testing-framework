<?php

declare(strict_types=1);

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

namespace TYPO3\TestingFramework\Core\Functional\Framework\Frontend;

use GuzzleHttp\Psr7\Query;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\Internal\InstructionInterface;

/**
 * Model of internal frontend request context.
 *
 * This is a helper class in testing-framework context used to execute frontend requests.
 * It provides some convenient helper methods like ->withPageId() to easily set up a frontend request.
 * It is later turned into a request implementing PSR-7 ServerRequestInterface.
 */
class InternalRequest extends ServerRequest
{
    public function __construct(?string $uri = null)
    {
        if ($uri === null) {
            $uri = 'http://localhost/';
        }
        $body = new Stream('php://temp', 'rw');
        parent::__construct($uri, 'GET', $body);
    }

    public function withPageId(int $pageId): InternalRequest
    {
        return $this->withQueryParameter('id', $pageId);
    }

    public function withMountPoint(int $targetPageId, int $sourcePageId): InternalRequest
    {
        return $this->withQueryParameter(
            'MP',
            sprintf('%d-%d', $targetPageId, $sourcePageId)
        );
    }

    public function withLanguageId(int $languageId): InternalRequest
    {
        return $this->withQueryParameter('L', $languageId);
    }

    /**
     * Adds or overrides parameter on existing query.
     */
    public function withQueryParameter(string $parameterName, int|float|string|null $value): InternalRequest
    {
        $query = $this->modifyQueryParameter(
            $this->uri->getQuery(),
            $parameterName,
            $value
        );

        $target = clone $this;
        $target->uri = $target->uri->withQuery($query);
        return $target;
    }

    public function withQueryParameters(array $parameters): InternalRequest
    {
        if (empty($parameters)) {
            return $this;
        }

        $query = $this->uri->getQuery();

        foreach ($parameters as $parameterName => $value) {
            $query = $this->modifyQueryParameter(
                $query,
                $parameterName,
                $value
            );
        }

        $target = clone $this;
        $target->uri = $target->uri->withQuery($query);
        return $target;
    }

    /**
     * @param InstructionInterface[] $instructions
     */
    public function withInstructions(array $instructions): InternalRequest
    {
        $currentAttribute = $this->getAttribute('testing-framework-instructions', []);
        foreach ($instructions as $instruction) {
            $currentAttribute[$instruction->getIdentifier()] = $instruction;
        }
        return $this->withAttribute('testing-framework-instructions', $currentAttribute);
    }

    public function getInstruction(string $identifier): ?InstructionInterface
    {
        $currentAttribute = $this->getAttribute('testing-framework-instructions', []);
        return $currentAttribute[$identifier] ?? null;
    }

    public function withServerParams(array $serverParams): InternalRequest
    {
        $target = clone $this;
        $target->serverParams = $serverParams;
        return $target;
    }

    private function modifyQueryParameter(string $query, string $parameterName, int|float|string|null $value): string
    {
        $parameters = Query::parse($query);
        $parameters[$parameterName] = $value;
        return Query::build($parameters);
    }

    public function getUri(): UriInterface
    {
        return parent::getUri();
    }
}
