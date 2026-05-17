<?php

namespace Lalalili\CourseCore\Readiness;

use Illuminate\Database\Eloquent\Model;
use Lalalili\CourseCore\Contracts\CourseProductResolver;
use Lalalili\CourseCore\Contracts\CourseReadinessCheck;
use Lalalili\CourseCore\Data\CourseReadinessContext;
use Lalalili\CourseCore\Support\CourseReadinessReport;

class ProductCheck implements CourseReadinessCheck
{
    public function __construct(private readonly CourseProductResolver $productResolver)
    {
    }

    public function check(Model $course, CourseReadinessReport $report, CourseReadinessContext $context): void
    {
        if (! $context->requireProduct) {
            return;
        }

        if (! $this->productResolver->productForCourse($course) instanceof Model) {
            $report->addBlockingIssue('Course product binding is required.');
        }
    }
}
