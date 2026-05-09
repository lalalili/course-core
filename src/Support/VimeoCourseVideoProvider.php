<?php

namespace Lalalili\CourseCore\Support;

use Exception;
use Illuminate\Support\Str;
use Lalalili\CourseCore\Contracts\CourseVideoProvider;
use Lalalili\CourseCore\Data\CourseVideoDetails;

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
        if (! class_exists(\Vimeo\Laravel\Facades\Vimeo::class)) {
            logger()->warning('Vimeo provider is configured, but vimeo/laravel is not installed.');

            return null;
        }

        try {
            $video = \Vimeo\Laravel\Facades\Vimeo::request("/videos/{$videoId}", [], 'GET');

            if ($video['status'] === 200 && ! empty($video['body']['duration']) && ! empty($video['body']['player_embed_url'])) {
                return new CourseVideoDetails(
                    duration: (int) $video['body']['duration'],
                    playerEmbedUrl: (string) $video['body']['player_embed_url'],
                );
            }
        } catch (Exception $exception) {
            logger()->error('Vimeo API request failed.', [
                'exception' => $exception::class,
                'message'   => $exception->getMessage(),
            ]);
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
