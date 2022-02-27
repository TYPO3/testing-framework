<?php

namespace TYPO3\TestingFramework\Core\Functional\Framework;

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
 * Model of internal frontend request
 */
trait AssignablePropertyTrait
{
    /**
     * @param array $data
     * @param callable|null $cast
     * @return static
     */
    private function assign(array $data, callable $cast = null)
    {
        if ($cast !== null) {
            $data = array_map($cast, $data);
        }
        $assignables = array_intersect_key(
            array_filter($data),
            get_object_vars($this)
        );
        if (empty($assignables)) {
            return $this;
        }
        $target = clone $this;
        foreach ($assignables as $name => $value) {
            $target->{$name} = $value;
        }
        return $target;
    }

    /**
     * @param array $data
     * @return static
     */
    private function with(array $data)
    {
        $target = $this;
        $data = array_filter($data);
        foreach ($data as $name => $value) {
            $methodName = 'with' . ucfirst($name);
            if (!method_exists($target, $methodName)) {
                throw new \RuntimeException(
                    sprintf('Method "%s" not found', $methodName),
                    1533632522
                );
            }
            $target = $target->$methodName($value);
        }
        return $target;
    }
}
