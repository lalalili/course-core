<?php

namespace Lalalili\CourseCore\Support;

use Lalalili\CourseCore\Contracts\CourseVideoPlatformManager;
use Lalalili\CourseCore\Contracts\CourseVideoProvider;
use Lalalili\CourseCore\Data\CourseVideoDetails;

class ConfiguredCourseVideoProvider implements CourseVideoProvider
{
    public function __construct(private readonly CourseVideoPlatformManager $manager)
    {
    }

    public function extractVideoId(?string $url): ?string
    {
        return $this->manager->providerForUrl($url)->extractVideoId($url);
    }

    public function getVideoDetails(string $videoId): ?CourseVideoDetails
    {
        return $this->manager->provider()->getVideoDetails($videoId);
    }

    public function getEmbedUrl(string $videoId, array $options = []): string
    {
        $provider = is_string($options['provider'] ?? null)
            ? (string) $options['provider']
            : null;

        return $this->manager->provider($provider)->getEmbedUrl($videoId, $options);
    }
}
