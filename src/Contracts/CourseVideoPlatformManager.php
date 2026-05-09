<?php

namespace Lalalili\CourseCore\Contracts;

interface CourseVideoPlatformManager
{
    public function defaultProviderKey(): string;

    public function provider(?string $provider = null): CourseVideoPlatform;

    public function providerForUrl(?string $url): CourseVideoPlatform;
}
