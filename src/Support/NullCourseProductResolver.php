<?php

namespace Lalalili\CourseCore\Support;

use Illuminate\Database\Eloquent\Model;
use Lalalili\CourseCore\Contracts\CourseProductResolver;

class NullCourseProductResolver implements CourseProductResolver
{
    public function productForCourse(Model $course): ?Model
    {
        return null;
    }
}
