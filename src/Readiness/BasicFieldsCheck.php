<?php

namespace Lalalili\CourseCore\Readiness;

use Illuminate\Database\Eloquent\Model;
use Lalalili\CourseCore\Contracts\CourseReadinessCheck;
use Lalalili\CourseCore\Data\CourseReadinessContext;
use Lalalili\CourseCore\Support\CourseReadinessReport;

class BasicFieldsCheck implements CourseReadinessCheck
{
    public function check(Model $course, CourseReadinessReport $report, CourseReadinessContext $context): void
    {
        if (blank(data_get($course, 'title')) && blank(data_get($course, 'name'))) {
            $report->addBlockingIssue('Course title is required.');
        }

        if (
            blank(data_get($course, 'course_category_id'))
            && blank(data_get($course, 'category_id'))
            && ! ($course->relationLoaded('category') && $course->getRelation('category') instanceof Model)
        ) {
            $report->addBlockingIssue('Course category is required.');
        }
    }
}
