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
    ) {}

    public function isReady(): bool
    {
        return $this->blockingIssues === [];
    }

    public function hasWarnings(): bool
    {
        return $this->warnings !== [];
    }

    public function hasSuggestions(): bool
    {
        return $this->suggestions !== [];
    }

    public function summary(): string
    {
        if ($this->isReady() && ! $this->hasWarnings() && ! $this->hasSuggestions()) {
            return '上架檢查通過。';
        }

        $lines = [];

        foreach ($this->blockingIssues as $issue) {
            $lines[] = $issue;
        }

        foreach ($this->warnings as $warning) {
            $lines[] = $warning;
        }

        foreach ($this->suggestions as $suggestion) {
            $lines[] = $suggestion;
        }

        return implode("\n", $lines);
    }

    /**
     * @return array{ready: bool, blocking_issues: list<string>, warnings: list<string>, suggestions: list<string>}
     */
    public function toArray(): array
    {
        return [
            'ready' => $this->isReady(),
            'blocking_issues' => $this->blockingIssues,
            'warnings' => $this->warnings,
            'suggestions' => $this->suggestions,
        ];
    }
}
