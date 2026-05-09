<?php

namespace Lalalili\CourseCore\Support;

use Exception;
use Illuminate\Support\Str;
use Lalalili\CourseCore\Contracts\CourseVideoProvider;
use Lalalili\CourseCore\Data\CourseVideoDetails;
use Vimeo\Laravel\Facades\Vimeo;

class VimeoCourseVideoProvider implements CourseVideoProvider
{
    public function extractVideoId(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        if (Str::of($url)->contains('/video/')) {
            return $url;
        }

        if (preg_match('/\.com\/([0-9]+)/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function getVideoDetails(string $videoId): ?CourseVideoDetails
    {
        try {
            $video = Vimeo::request("/videos/{$videoId}", [], 'GET');

            if ($video['status'] === 200 && ! empty($video['body']['duration']) && ! empty($video['body']['player_embed_url'])) {
                return new CourseVideoDetails(
                    duration: (int) $video['body']['duration'],
                    playerEmbedUrl: (string) $video['body']['player_embed_url'],
                );
            }
        } catch (Exception $exception) {
            logger()->error("Vimeo API Error: {$exception->getMessage()}");
        }

        return null;
    }

    public function getEmbedUrl(string $videoId, array $options = []): string
    {
        $query = http_build_query([
            'autoplay'  => $options['autoplay'] ?? false,
            'loop'      => $options['loop'] ?? false,
            'title'     => $options['show_title'] ?? false,
            'byline'    => $options['byline'] ?? false,
            'portrait'  => $options['portrait'] ?? false,
            'autopause' => false,
        ]);

        return "https://player.vimeo.com/video/{$videoId}?{$query}";
    }
}
