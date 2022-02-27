<?php

namespace TYPO3\TestingFramework\Core\Functional\Framework\Constraint\RequestSection;

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

use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\ResponseSection;

/**
 * Model of frontend response
 */
class DoesNotHaveRecordConstraint extends AbstractRecordConstraint
{
    /**
     * @param ResponseSection $responseSection
     * @return bool
     */
    protected function matchesSection(ResponseSection $responseSection)
    {
        $records = $responseSection->getRecords();

        if (empty($records) || !is_array($records)) {
            $this->sectionFailures[$responseSection->getIdentifier()] = 'No records found.';
            return false;
        }

        $nonMatchingValues = $this->getNonMatchingValues($records);
        $matchingValues = array_diff($this->values, $nonMatchingValues);

        if (!empty($matchingValues)) {
            $this->sectionFailures[$responseSection->getIdentifier()] = 'Could not assert not having values for "' . $this->table . '.' . $this->field . '": ' . implode(', ', $matchingValues);
            return false;
        }

        return true;
    }

    /**
     * Returns a string representation of the constraint.
     *
     * @return string
     */
    public function toString(): string
    {
        return 'response does not have record';
    }
}
