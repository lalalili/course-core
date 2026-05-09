<?php

namespace Lalalili\CourseCore\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

interface CourseAccessResolver
{
    public function canViewCourse(?Authenticatable $user, Model $course): bool;

    public function canAccessUnit(?Authenticatable $user, Model $course, Model $unit): bool;

    public function hasPurchasedCourse(?Authenticatable $user, Model $course): bool;
}
