<?php

use Illuminate\Database\Eloquent\Model;
use Lalalili\CourseCore\Contracts\CourseReadinessCheck;
use Lalalili\CourseCore\Data\CourseReadinessContext;
use Lalalili\CourseCore\Services\CourseReadinessService;
use Lalalili\CourseCore\Support\CourseReadinessReport;

it('runs custom checks from iterable', function (): void {
    $customCheck = new class implements CourseReadinessCheck
    {
        public function check(Model $course, CourseReadinessReport $report, CourseReadinessContext $context): void
        {
            $report->addBlockingIssue('custom blocking issue');
            $report->addWarning('custom warning');
            $report->addSuggestion('custom suggestion');
        }
    };

    $service = new CourseReadinessService([$customCheck]);
    $course = new class extends Model
    {
        protected $guarded = [];
    };

    $result = $service->evaluate($course);

    expect($result->blockingIssues)->toBe(['custom blocking issue'])
        ->and($result->warnings)->toBe(['custom warning'])
        ->and($result->suggestions)->toBe(['custom suggestion'])
        ->and($result->isReady())->toBeFalse()
        ->and($result->hasWarnings())->toBeTrue()
        ->and($result->hasSuggestions())->toBeTrue();
});

it('passes context flags to checks', function (): void {
    $capturedCheck = new class implements CourseReadinessCheck
    {
        public ?CourseReadinessContext $lastContext = null;

        public function check(Model $course, CourseReadinessReport $report, CourseReadinessContext $context): void
        {
            $this->lastContext = $context;
        }
    };

    $service = new CourseReadinessService([$capturedCheck]);
    $course = new class extends Model
    {
        protected $guarded = [];
    };

    $service->evaluate($course, requireProduct: true, requireReadyVideos: true);

    expect($capturedCheck->lastContext)
        ->toBeInstanceOf(CourseReadinessContext::class)
        ->and($capturedCheck->lastContext->requireProduct)->toBeTrue()
        ->and($capturedCheck->lastContext->requireReadyVideos)->toBeTrue();
});

it('deduplicates repeated messages', function (): void {
    $check = new class implements CourseReadinessCheck
    {
        public function check(Model $course, CourseReadinessReport $report, CourseReadinessContext $context): void
        {
            $report->addBlockingIssue('duplicate issue');
            $report->addBlockingIssue('duplicate issue');
            $report->addWarning('duplicate warning');
            $report->addWarning('duplicate warning');
        }
    };

    $result = (new CourseReadinessService([$check]))->evaluate(new class extends Model
    {
        protected $guarded = [];
    });

    expect($result->blockingIssues)->toBe(['duplicate issue'])
        ->and($result->warnings)->toBe(['duplicate warning']);
});

it('returns ready result with passing summary when no issues found', function (): void {
    $result = (new CourseReadinessService([]))->evaluate(new class extends Model
    {
        protected $guarded = [];
    });

    expect($result->isReady())->toBeTrue()
        ->and($result->hasWarnings())->toBeFalse()
        ->and($result->hasSuggestions())->toBeFalse()
        ->and($result->summary())->toBe('上架檢查通過。');
});
