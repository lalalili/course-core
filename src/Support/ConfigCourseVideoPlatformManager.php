<?php

namespace Lalalili\CourseCore\Support;

use InvalidArgumentException;
use Lalalili\CourseCore\Contracts\CourseVideoPlatform;
use Lalalili\CourseCore\Contracts\CourseVideoPlatformManager;

class ConfigCourseVideoPlatformManager implements CourseVideoPlatformManager
{
    public function defaultProviderKey(): string
    {
        return (string) config('course-core.default_video_platform', 'vimeo');
    }

    public function provider(?string $provider = null): CourseVideoPlatform
    {
        $provider ??= $this->defaultProviderKey();
        $platforms = config('course-core.video_platforms', []);
        $class = is_array($platforms) ? ($platforms[$provider] ?? null) : null;

        if (! is_string($class) || ! class_exists($class)) {
            throw new InvalidArgumentException("Course video platform [{$provider}] is not configured.");
        }

        $instance = app($class);

        if (! $instance instanceof CourseVideoPlatform) {
            throw new InvalidArgumentException("Course video platform [{$provider}] must implement CourseVideoPlatform.");
        }

        return $instance;
    }

    public function providerForUrl(?string $url): CourseVideoPlatform
    {
        $platforms = config('course-core.video_platforms', []);

        if (is_array($platforms)) {
            foreach (array_keys($platforms) as $provider) {
                $platform = $this->provider((string) $provider);

                if ($platform->extractVideoId($url) !== null) {
                    return $platform;
                }
            }
        }

        return $this->provider();
    }
}
