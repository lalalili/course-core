<?php

namespace Lalalili\CourseCore\Contracts;

use Illuminate\Database\Eloquent\Model;
use Lalalili\CourseCore\Data\CourseReadinessContext;
use Lalalili\CourseCore\Support\CourseReadinessReport;

interface CourseReadinessCheck
{
    public function check(Model $course, CourseReadinessReport $report, CourseReadinessContext $context): void;
}
