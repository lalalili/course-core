<?php

namespace Lalalili\CourseCore\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CourseSearchContract
{
    /**
     * @return array{courses: LengthAwarePaginator<int, mixed>, keyword: string}
     */
    public function searchCourses(string $keyword): array;
}
