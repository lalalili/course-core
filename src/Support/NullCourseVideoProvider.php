<?php

namespace Lalalili\CourseCore\Support;

use Lalalili\CourseCore\Contracts\CourseVideoProvider;
use Lalalili\CourseCore\Data\CourseVideoDetails;

class NullCourseVideoProvider implements CourseVideoProvider
{
    public function extractVideoId(?string $url): ?string
    {
        return null;
    }

    public function getVideoDetails(string $videoId): ?CourseVideoDetails
    {
        return null;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function getEmbedUrl(string $videoId, array $options = []): string
    {
        return '';
    }
}
