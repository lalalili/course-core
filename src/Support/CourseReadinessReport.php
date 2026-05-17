<?php

namespace Lalalili\CourseCore\Support;

use Lalalili\CourseCore\Data\CourseReadinessResult;

class CourseReadinessReport
{
    /** @var list<string> */
    private array $blockingIssues = [];

    /** @var list<string> */
    private array $warnings = [];

    /** @var list<string> */
    private array $suggestions = [];

    public function addBlockingIssue(string $issue): void
    {
        $this->blockingIssues[] = $issue;
    }

    public function addWarning(string $warning): void
    {
        $this->warnings[] = $warning;
    }

    public function addSuggestion(string $suggestion): void
    {
        $this->suggestions[] = $suggestion;
    }

    public function toResult(): CourseReadinessResult
    {
        return new CourseReadinessResult(
            blockingIssues: array_values(array_unique($this->blockingIssues)),
            warnings: array_values(array_unique($this->warnings)),
            suggestions: array_values(array_unique($this->suggestions)),
        );
    }
}
