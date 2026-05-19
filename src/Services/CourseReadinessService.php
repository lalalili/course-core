<?php

namespace Lalalili\CourseCore\Services;

use Illuminate\Database\Eloquent\Model;
use Lalalili\CourseCore\Contracts\CourseReadinessCheck;
use Lalalili\CourseCore\Data\CourseReadinessContext;
use Lalalili\CourseCore\Data\CourseReadinessResult;
use Lalalili\CourseCore\Readiness\BasicFieldsCheck;
use Lalalili\CourseCore\Readiness\DetailCheck;
use Lalalili\CourseCore\Readiness\ProductCheck;
use Lalalili\CourseCore\Readiness\UnitsCheck;
use Lalalili\CourseCore\Support\CourseReadinessReport;

class CourseReadinessService
{
    /** @var list<class-string<CourseReadinessCheck>> */
    public const DEFAULT_CHECKS = [
        BasicFieldsCheck::class,
        DetailCheck::class,
        ProductCheck::class,
        UnitsCheck::class,
    ];

    /**
     * @param  iterable<CourseReadinessCheck>  $checks
     * @param  list<string>  $eagerLoad
     */
    public function __construct(
        private readonly iterable $checks,
        private readonly array $eagerLoad = [],
    ) {}

    public function evaluate(
        Model $course,
        bool $requireProduct = false,
        bool $requireReadyVideos = false,
    ): CourseReadinessResult {
        if ($this->eagerLoad !== []) {
            $course->loadMissing($this->eagerLoad);
        }

        $report = new CourseReadinessReport;
        $context = new CourseReadinessContext(
            requireProduct: $requireProduct,
            requireReadyVideos: $requireReadyVideos,
        );

        foreach ($this->checks as $check) {
            $check->check($course, $report, $context);
        }

        return $report->toResult();
    }
}
