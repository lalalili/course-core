<?php

use Lalalili\CourseCore\Support\CloudflareStreamVideoProvider;
use Lalalili\CourseCore\Support\ConfiguredCourseVideoProvider;
use Lalalili\CourseCore\Support\NullCourseAccessResolver;
use Lalalili\CourseCore\Support\NullCourseProductResolver;
use Lalalili\CourseCore\Support\NullCourseTenantResolver;
use Lalalili\CourseCore\Support\VdoCipherVideoProvider;
use Lalalili\CourseCore\Support\VimeoCourseVideoProvider;

return [
    'models' => [
        'course' => null,
        'category' => null,
        'chapter' => null,
        'detail' => null,
        'history' => null,
    ],

    'coming_days' => env('COURSE_COMING_DAYS', 30),

    'access_resolver' => NullCourseAccessResolver::class,
    'tenant_resolver' => NullCourseTenantResolver::class,
    'product_resolver' => NullCourseProductResolver::class,
    'video_provider' => ConfiguredCourseVideoProvider::class,

    'default_video_platform' => env('COURSE_VIDEO_PLATFORM', 'vimeo'),

    'video_upload_strategy' => env('COURSE_VIDEO_UPLOAD_STRATEGY', 's3_then_import'),

    'video_staging_disk' => env('COURSE_VIDEO_STAGING_DISK', 's3'),

    'video_staging_prefix' => env('COURSE_VIDEO_STAGING_PREFIX', 'course-videos/staging'),

    'video_staging_temporary_url_minutes' => env('COURSE_VIDEO_STAGING_TEMPORARY_URL_MINUTES', 60),

    'video_cleanup_staging_after_import' => env('COURSE_VIDEO_CLEANUP_STAGING_AFTER_IMPORT', true),

    'video_platforms' => [
        'vimeo' => VimeoCourseVideoProvider::class,
        'cloudflare_stream' => CloudflareStreamVideoProvider::class,
        'vdocipher' => VdoCipherVideoProvider::class,
    ],

    'providers' => [
        'cloudflare_stream' => [
            'account_id' => env('CLOUDFLARE_STREAM_ACCOUNT_ID'),
            'api_token' => env('CLOUDFLARE_STREAM_API_TOKEN'),
            'customer_subdomain' => env('CLOUDFLARE_STREAM_CUSTOMER_SUBDOMAIN'),
        ],
        'vdocipher' => [
            'api_secret' => env('VDOCIPHER_API_SECRET'),
        ],
    ],
];
