<?php

namespace Lalalili\CourseCore\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Lalalili\CourseCore\Contracts\CourseTenantResolver;

class NullCourseTenantResolver implements CourseTenantResolver
{
    public function currentCompanyId(): ?int
    {
        return null;
    }

    public function isSuperAdmin(?Authenticatable $user): bool
    {
        return false;
    }

    public function canAccessAdmin(?Authenticatable $user): bool
    {
        return true;
    }
}
