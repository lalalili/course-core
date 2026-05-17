<?php

namespace Lalalili\CourseCore\Readiness;

use Illuminate\Database\Eloquent\Model;
use Lalalili\CourseCore\Contracts\CourseReadinessCheck;
use Lalalili\CourseCore\Data\CourseReadinessContext;
use Lalalili\CourseCore\Support\CourseReadinessReport;

class DetailCheck implements CourseReadinessCheck
{
    public function check(Model $course, CourseReadinessReport $report, CourseReadinessContext $context): void
    {
        $detail = $course->relationLoaded('detail') ? $course->getRelation('detail') : null;

        if (! $detail instanceof Model) {
            $report->addBlockingIssue('Course detail is required.');

            return;
        }

        if (
            blank(data_get($detail, 'content'))
            && blank(data_get($detail, 'description'))
            && blank(data_get($detail, 'product_desc'))
        ) {
            $report->addSuggestion('Course detail content is empty.');
        }
    }
}
