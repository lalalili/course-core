<?php

namespace Lalalili\CourseCore\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface CourseTenantResolver
{
    public function currentCompanyId(): ?int;

    public function isSuperAdmin(?Authenticatable $user): bool;

    public function canAccessAdmin(?Authenticatable $user): bool;
}
