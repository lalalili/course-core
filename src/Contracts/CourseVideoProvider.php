<?php

namespace Lalalili\CourseCore\Contracts;

use Lalalili\CourseCore\Data\CourseVideoDetails;

interface CourseVideoProvider
{
    public function extractVideoId(?string $url): ?string;

    public function getVideoDetails(string $videoId): ?CourseVideoDetails;

    /**
     * @param  array<string, bool|int|string>  $options
     */
    public function getEmbedUrl(string $videoId, array $options = []): string;
}
