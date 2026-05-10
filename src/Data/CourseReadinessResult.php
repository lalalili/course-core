<?php

namespace Lalalili\CourseCore\Data;

final readonly class CourseReadinessResult
{
    /**
     * @param  list<string>  $blockingIssues
     * @param  list<string>  $warnings
     * @param  list<string>  $suggestions
     */
    public function __construct(
        public array $blockingIssues = [],
        public array $warnings = [],
        public array $suggestions = [],
    ) {
    }

    public function isReady(): bool
    {
        return $this->blockingIssues === [];
    }

    /**
     * @return array{ready: bool, blocking_issues: list<string>, warnings: list<string>, suggestions: list<string>}
     */
    public function toArray(): array
    {
        return [
            'ready'           => $this->isReady(),
            'blocking_issues' => $this->blockingIssues,
            'warnings'        => $this->warnings,
            'suggestions'     => $this->suggestions,
        ];
    }
}
