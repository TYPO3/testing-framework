<?php
namespace TYPO3\TestingFramework\Core\Functional\Framework\Frontend\Internal;

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

use TYPO3\TestingFramework\Core\Functional\Framework\AssignablePropertyTrait;

/**
 * Model of instruction
 */
abstract class AbstractInstruction implements \JsonSerializable
{
    use AssignablePropertyTrait;

    /**
     * @var string
     */
    protected $identifier;

    /**
     * @param array $data
     * @return static
     */
    public static function fromArray(array $data): self
    {
        if (empty($data['__type'])) {
            throw new \LogicException(
                'Missing internal "__type" reference',
                1534516564
            );
        }
        if (!is_a($data['__type'], self::class, true)) {
            throw new \LogicException(
                sprintf(
                    'Class "%s" does not inherit from "%s"',
                    $data['__type'],
                    self::class
                ),
                1534516565
            );
        }
        if (empty($data['identifier'])) {
            throw new \LogicException(
                'Missing identifier',
                1534516566
            );
        }

        if (self::class === static::class) {
            return $data['__type']::fromArray($data);
        }
        $target = new static($data['identifier']);
        unset($data['__type'], $data['identifier']);
        return $target->with($data);
    }

    /**
     * @param string $identifier
     */
    public function __construct(string $identifier)
    {
        $this->identifier = $identifier;
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return array_merge(
            get_object_vars($this),
            ['__type' => get_class($this)]
        );
    }

    /**
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }
}
