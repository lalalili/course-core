<?php

namespace Lalalili\CourseCore;

use Lalalili\CourseCore\Commands\InstallCourseCoreCommand;
use Lalalili\CourseCore\Contracts\CourseAccessResolver;
use Lalalili\CourseCore\Contracts\CourseProductResolver;
use Lalalili\CourseCore\Contracts\CourseTenantResolver;
use Lalalili\CourseCore\Contracts\CourseVideoPlatformManager;
use Lalalili\CourseCore\Contracts\CourseVideoProvider;
use Lalalili\CourseCore\Services\CourseReadinessService;
use Lalalili\CourseCore\Support\ConfigCourseVideoPlatformManager;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class CourseCoreServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('course-core')
            ->hasConfigFile('course-core')
            ->hasCommand(InstallCourseCoreCommand::class);
    }

    public function registeringPackage(): void
    {
        $this->app->bind(CourseAccessResolver::class, fn ($app) => $app->make(config('course-core.access_resolver')));
        $this->app->bind(CourseTenantResolver::class, fn ($app) => $app->make(config('course-core.tenant_resolver')));
        $this->app->bind(CourseProductResolver::class, fn ($app) => $app->make(config('course-core.product_resolver')));
        $this->app->singleton(CourseVideoPlatformManager::class, ConfigCourseVideoPlatformManager::class);
        $this->app->bind(CourseVideoProvider::class, fn ($app) => $app->make(config('course-core.video_provider')));

        $this->app->singleton(CourseReadinessService::class, function ($app) {
            $checkClasses = config('course-core.readiness.checks');
            $eagerLoad = (array) config('course-core.readiness.eager_load', []);

            $checks = $checkClasses === null
                ? array_map(fn ($class) => $app->make($class), CourseReadinessService::DEFAULT_CHECKS)
                : array_map(fn ($class) => $app->make($class), (array) $checkClasses);

            return new CourseReadinessService($checks, $eagerLoad);
        });
    }
}
