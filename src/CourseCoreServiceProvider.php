<?php

namespace Lalalili\CourseCore;

use Lalalili\CourseCore\Contracts\CourseAccessResolver;
use Lalalili\CourseCore\Contracts\CourseProductResolver;
use Lalalili\CourseCore\Contracts\CourseTenantResolver;
use Lalalili\CourseCore\Contracts\CourseVideoProvider;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class CourseCoreServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('course-core')
            ->hasConfigFile('course-core');
    }

    public function registeringPackage(): void
    {
        $this->app->bind(CourseAccessResolver::class, fn ($app) => $app->make(config('course-core.access_resolver')));
        $this->app->bind(CourseTenantResolver::class, fn ($app) => $app->make(config('course-core.tenant_resolver')));
        $this->app->bind(CourseProductResolver::class, fn ($app) => $app->make(config('course-core.product_resolver')));
        $this->app->bind(CourseVideoProvider::class, fn ($app) => $app->make(config('course-core.video_provider')));
    }
}
