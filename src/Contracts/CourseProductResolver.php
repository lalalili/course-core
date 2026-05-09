<?php

namespace Lalalili\CourseCore\Contracts;

use Illuminate\Database\Eloquent\Model;

interface CourseProductResolver
{
    public function productForCourse(Model $course): ?Model;
}
