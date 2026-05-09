<?php

namespace Lalalili\CourseCore\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Lalalili\CourseCore\Contracts\CourseAccessResolver;

class NullCourseAccessResolver implements CourseAccessResolver
{
    public function canViewCourse(?Authenticatable $user, Model $course): bool
    {
        return true;
    }

    public function canAccessUnit(?Authenticatable $user, Model $course, Model $unit): bool
    {
        return (bool) data_get($unit, 'isFree', false);
    }

    public function hasPurchasedCourse(?Authenticatable $user, Model $course): bool
    {
        return false;
    }
}
