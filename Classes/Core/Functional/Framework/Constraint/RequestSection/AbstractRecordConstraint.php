<?php

declare(strict_types=1);

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

use PHPUnit\Framework\Constraint\Constraint;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\ResponseSection;

/**
 * Model of frontend response
 */
abstract class AbstractRecordConstraint extends Constraint
{
    protected array $sectionFailures = [];
    protected string $table;
    protected string $field;
    protected bool $strict = false;
    protected array $values;

    public function setTable(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    public function setField(string $field): self
    {
        $this->field = $field;
        return $this;
    }

    public function setValues(...$values): self
    {
        $this->values = $values;
        return $this;
    }

    public function setStrict(bool $strict): self
    {
        $this->strict = $strict;
        return $this;
    }

    /**
     * Evaluates the constraint for parameter $other. Returns true if the
     * constraint is met, false otherwise.
     *
     * @param array|ResponseSection|ResponseSection[] $other ResponseSections to evaluate
     */
    protected function matches($other): bool
    {
        if (is_array($other)) {
            $success = null;
            foreach ($other as $item) {
                $currentSuccess = $this->matchesSection($item);
                $success = ($success === null ? $currentSuccess : $success || $currentSuccess);
            }
            return !empty($success);
        }
        return $this->matchesSection($other);
    }

    abstract protected function matchesSection(ResponseSection $responseSection): bool;

    protected function getNonMatchingValues(array $records): array
    {
        $values = $this->values;
        foreach ($records as $recordIdentifier => $recordData) {
            if (!str_starts_with($recordIdentifier, $this->table . ':')) {
                continue;
            }
            if (isset($recordData[$this->field])
                && (($foundValueIndex = array_search($recordData[$this->field], $values)) !== false)
            ) {
                unset($values[$foundValueIndex]);
            }
        }
        return $values;
    }

    protected function getRemainingRecords(array $records): array
    {
        $values = $this->values;
        foreach ($records as $recordIdentifier => $recordData) {
            if (!str_starts_with($recordIdentifier, $this->table . ':')) {
                unset($records[$recordIdentifier]);
                continue;
            }
            if (($foundValueIndex = array_search($recordData[$this->field], $values)) !== false) {
                unset($values[$foundValueIndex]);
                unset($records[$recordIdentifier]);
            }
        }
        return $records;
    }

    /**
     * Returns the description of the failure
     *
     * The beginning of failure messages is "Failed asserting that" in most
     * cases. This method should return the second part of that sentence.
     *
     * @param mixed $other Evaluated value or object.
     */
    protected function failureDescription(mixed $other): string
    {
        return $this->toString();
    }

    /**
     * Return additional failure description where needed
     *
     * The function can be overridden to provide additional failure
     * information like a diff
     *
     * @param mixed $other Evaluated value or object.
     */
    protected function additionalFailureDescription(mixed $other): string
    {
        $failureDescription = '';
        foreach ($this->sectionFailures as $sectionIdentifier => $sectionFailure) {
            $failureDescription .= '* Section "' . $sectionIdentifier . '": ' . $sectionFailure . LF;
        }
        return $failureDescription;
    }
}
