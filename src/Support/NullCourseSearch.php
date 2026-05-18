<?php

namespace Lalalili\CourseCore\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use Lalalili\CourseCore\Contracts\CourseSearchContract;

class NullCourseSearch implements CourseSearchContract
{
    /**
     * @return array{courses: LengthAwarePaginator<int, mixed>, keyword: string}
     */
    public function searchCourses(string $keyword): array
    {
        return [
            'courses' => new LengthAwarePaginator([], 0, 15),
            'keyword' => $keyword,
        ];
    }
}
