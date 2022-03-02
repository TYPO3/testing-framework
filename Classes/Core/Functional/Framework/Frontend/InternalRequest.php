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

use TYPO3\CMS\Core\Http\Request;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\TestingFramework\Core\Functional\Framework\AssignablePropertyTrait;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\Internal\AbstractInstruction;

/**
 * Model of internal frontend request context.
 *
 * This is a helper class in testing-framework context used to execute frontend requests.
 * It provides some convenient helper methods like ->withPageId() to easily set up a frontend request.
 * It is later turned into a request implementing PSR-7 ServerRequestInterface.
 */
class InternalRequest extends Request implements \JsonSerializable
{
    use AssignablePropertyTrait;

    /**
     * @var AbstractInstruction[]
     */
    protected $instructions = [];

    /**
     * @var array|null
     */
    protected $parsedBody;

    /**
     * @param array $data
     * @return InternalRequest
     * @internal
     * @deprecated Can be removed when retrieveFrontendRequestResult() is dropped
     */
    public static function fromArray(array $data): InternalRequest
    {
        $target = (new static($data['uri'] ?? ''));
        $target->getBody()->write($data['body'] ?? '');
        $data['instructions'] = static::buildInstructions($data);
        unset($data['uri'], $data['body']);
        return $target->with($data);
    }

    /**
     * @param array $data
     * @return AbstractInstruction[]
     */
    private static function buildInstructions(array $data): array
    {
        return array_map(
            function (array $data) {
                return AbstractInstruction::fromArray($data);
            },
            $data['instructions'] ?? []
        );
    }

    /**
     * @param string|null $uri URI for the request, if any.
     */
    public function __construct($uri = null)
    {
        if ($uri === null) {
            $uri = 'http://localhost/';
        }
        $body = new Stream('php://temp', 'rw');
        parent::__construct($uri, 'GET', $body);
    }

    /**
     * @return array
     * @internal
     * @deprecated Can be removed when retrieveFrontendRequestResult() is dropped, also drop 'implements JsonSerializable'
     */
    public function jsonSerialize(): array
    {
        return [
            'method' => $this->method,
            'headers' => $this->headers,
            'uri' => (string)$this->uri,
            'body' => (string)$this->body,
            'instructions' => $this->instructions,
        ];
    }

    /**
     * @param int $pageId
     * @return InternalRequest
     */
    public function withPageId(int $pageId): InternalRequest
    {
        return $this->withQueryParameter('id', $pageId);
    }

    /**
     * @param int $targetPageId Page the mount points to
     * @param int $sourePageId Page the mount is defined at
     * @return InternalRequest
     */
    public function withMountPoint(int $targetPageId, int $sourePageId): InternalRequest
    {
        return $this->withQueryParameter(
            'MP',
            sprintf('%d-%d', $targetPageId, $sourePageId)
        );
    }

    /**
     * @param int $languageId
     * @return InternalRequest
     */
    public function withLanguageId(int $languageId): InternalRequest
    {
        return $this->withQueryParameter('L', $languageId);
    }

    /**
     * Adds or overrides parameter on existing query.
     *
     * @param string $parameterName
     * @param int|float|string|null $value
     * @return InternalRequest
     */
    public function withQueryParameter(string $parameterName, $value): InternalRequest
    {
        $query = $this->modifyQueryParameter(
            $this->uri->getQuery() ?? '',
            $parameterName,
            $value
        );

        $target = clone $this;
        $target->uri = $target->uri->withQuery($query);
        return $target;
    }

    /**
     * Helper method for with() of AssignablePropertyTrait to allow setting withHeader()
     * from single $this->headers[] when this object is thawed using fromArray() in
     * PHP process forking scenarios initiated by executeFrontendRequest().
     *
     * NOT relevant for executeFrontendSubRequest() where the request object is directly
     * hand over to the frontend application.
     *
     * @param array $headers
     * @return InternalRequest
     * @deprecated Can be removed when retrieveFrontendRequestResult()
     */
    private function withHeaders(array $headers): InternalRequest
    {
        $request = $this;
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request;
    }

    /**
     * @param array $parameters
     * @return InternalRequest
     */
    public function withQueryParameters(array $parameters): InternalRequest
    {
        if (empty($parameters)) {
            return $this;
        }

        $query = $this->uri->getQuery() ?? '';

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
     * @param AbstractInstruction[] $instructions
     * @return InternalRequest
     */
    public function withInstructions(array $instructions): InternalRequest
    {
        $target = clone $this;
        foreach ($instructions as $instruction) {
            $target->instructions[$instruction->getIdentifier()] = $instruction;
        }
        return $target;
    }

    /**
     * @param string $identifier
     * @return AbstractInstruction|null
     */
    public function getInstruction(string $identifier): ?AbstractInstruction
    {
        return $this->instructions[$identifier] ?? null;
    }

    public function withParsedBody(?array $parsedBody=null): InternalRequest
    {
        $target = clone $this;
        $target->parsedBody = $parsedBody;
        return $target;
    }

    public function getParsedBody(): ?array
    {
        return $this->parsedBody;
    }

    /**
     * @param string $query
     * @param string $parameterName
     * @param int|float|string|null $value
     * @return string
     */
    private function modifyQueryParameter(string $query, string $parameterName, $value): string
    {
        if (!is_float($value) && !is_int($value) && !is_string($value) && $value !== null) {
            throw new \RuntimeException(
                sprintf('Invalid type "%s"', gettype($value)),
                1533639711
            );
        }

        $parameters = \GuzzleHttp\Psr7\Query::parse($query);
        $parameters[$parameterName] = $value;
        return \GuzzleHttp\Psr7\Query::build($parameters);
    }
}
