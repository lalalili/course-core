<?php

return [
    'models' => [
        'course'   => null,
        'category' => null,
        'chapter'  => null,
        'detail'   => null,
        'history'  => null,
    ],

    'coming_days' => env('COURSE_COMING_DAYS', 30),

    'access_resolver'  => Lalalili\CourseCore\Support\NullCourseAccessResolver::class,
    'tenant_resolver'  => Lalalili\CourseCore\Support\NullCourseTenantResolver::class,
    'product_resolver' => Lalalili\CourseCore\Support\NullCourseProductResolver::class,
    'video_provider'   => Lalalili\CourseCore\Support\VimeoCourseVideoProvider::class,
];
