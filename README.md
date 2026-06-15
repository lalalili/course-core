# Course Core

Reusable online course domain layer for Laravel applications.

## Features

- Course access, tenant, product, and video provider contracts.
- Configurable model class names for host applications.
- Safe null video provider by default, with Vimeo, Cloudflare Stream, and VdoCipher platform adapters.
- Playback unit initialization service that can be reused across projects.
- Install command with model and migration stubs for new applications.

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

For a new application, publish the starter files:

```bash
php artisan course-core:install
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
    'video_provider' => Lalalili\CourseCore\Support\NullCourseVideoProvider::class,
];
```

Install `vimeo/laravel` and set `video_provider` to `VimeoCourseVideoProvider::class` only when the host app uses Vimeo.

## Contracts

- `CourseAccessResolver`
- `CourseTenantResolver`
- `CourseProductResolver`
- `CourseVideoProvider`
- `CourseVideoPlatform`
- `CourseVideoPlatformManager`

Each host application owns its commerce, tenant, and permission rules by binding these contracts through config.

## Video Platform Lifecycle

`CourseVideoPlatform` is the stable abstraction for provider-specific video operations. The package owns only provider API behavior; host applications still own database records, upload sessions, authorization, queues, and course-unit binding.

Supported lifecycle methods:

- `createDirectUploadSession()` returns an optional provider direct upload session. Providers may return `null` when direct uploads are not supported or not enabled.
- `importFromUrl()` imports a staged file URL into the provider and returns provider ids, embed URLs, duration, thumbnail, and transcode metadata.
- `refreshStatus()` reads provider processing state and normalizes it to `CourseVideoStatus`.
- `updateVideo()` updates provider metadata such as title or description.
- `deleteVideo()` removes the provider-side video.
- `getEmbedUrl()` and `extractVideoId()` keep legacy URL playback compatible.

For large-file admin uploads, hosts should prefer staging files in object storage first, then calling `importFromUrl()` from a queued job. Direct provider uploads and TUS are intentionally kept behind the contract until a host has verified that provider flow in production.

Recommended host state mapping:

- Session `created` or `uploading`: object storage upload is not complete.
- Session `uploaded` or `importing`: the staged file is ready and a provider import job is running.
- Session `processing`: the provider has accepted the video but transcoding is not ready.
- Session `ready`: `refreshStatus()` reports `isReady`.
- Session `failed` or `cancelled`: host-side terminal states with the provider error or user action stored on the upload session.

## Releases

Use tagged versions in host applications:

```bash
composer require lalalili/course-core:^0.4
```

The current aitehub host pins `dev-main as 0.4.1` while the package stabilizes its 0.4 line.

## Tests

From the package directory:

```bash
./vendor/bin/pest
```
