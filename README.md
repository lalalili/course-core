# Course Core

Reusable online course domain layer for Laravel applications.

## Features

- Course access, tenant, product, and video provider contracts.
- Configurable model class names for host applications.
- Vimeo-backed default video provider.
- Playback unit initialization service that can be reused across projects.

## Installation

Require the package through Composer:

```bash
composer require lalalili/course-core
```

When installing directly from GitHub before a Packagist release, add the VCS repository to the host application's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/lalalili/course-core.git"
        }
    ]
}
```

Publish and customize the configuration in the host application:

```bash
php artisan vendor:publish --tag=course-core-config
```

At minimum, configure the host model classes and resolver implementations:

```php
return [
    'models' => [
        'course' => App\Models\Course::class,
        'category' => App\Models\CourseCategory::class,
        'chapter' => App\Models\CourseChapter::class,
        'detail' => App\Models\CourseDetail::class,
        'history' => App\Models\CourseHistory::class,
    ],

    'access_resolver' => App\Services\Courses\CourseAccessResolver::class,
    'tenant_resolver' => App\Services\Courses\CourseTenantResolver::class,
    'product_resolver' => App\Services\Courses\CourseProductResolver::class,
    'video_provider' => Lalalili\CourseCore\Support\VimeoCourseVideoProvider::class,
];
```

## Contracts

- `CourseAccessResolver`
- `CourseTenantResolver`
- `CourseProductResolver`
- `CourseVideoProvider`

Each host application owns its commerce, tenant, and permission rules by binding these contracts through config.
