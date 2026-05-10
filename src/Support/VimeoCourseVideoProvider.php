<?php

namespace Lalalili\CourseCore\Support;

use Exception;
use Illuminate\Support\Str;
use Lalalili\CourseCore\Contracts\CourseVideoPlatform;
use Lalalili\CourseCore\Data\CourseVideoDetails;
use Lalalili\CourseCore\Data\CourseVideoImportRequest;
use Lalalili\CourseCore\Data\CourseVideoImportResult;
use Lalalili\CourseCore\Data\CourseVideoStatus;
use Lalalili\CourseCore\Data\CourseVideoUploadRequest;
use Lalalili\CourseCore\Data\CourseVideoUploadSession;
use RuntimeException;
use Vimeo\Laravel\Facades\Vimeo;

class VimeoCourseVideoProvider implements CourseVideoPlatform
{
    public function key(): string
    {
        return 'vimeo';
    }

    public function extractVideoId(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        if (preg_match('/player\.vimeo\.com\/video\/([0-9]+)/', $url, $matches)) {
            return $matches[1];
        }

        if (preg_match('/\.com\/([0-9]+)/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function getVideoDetails(string $videoId): ?CourseVideoDetails
    {
        try {
            $this->ensureVimeoPackageIsInstalled();

            $video = Vimeo::request("/videos/{$videoId}", [], 'GET');

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

    public function createDirectUploadSession(CourseVideoUploadRequest $request): ?CourseVideoUploadSession
    {
        $this->ensureVimeoPackageIsInstalled();

        $response = Vimeo::request('/me/videos/', [
            'upload' => [
                'approach' => 'tus',
                'size'     => $request->fileSize,
            ],
            'name'        => $request->title ?? pathinfo($request->fileName, PATHINFO_FILENAME),
            'description' => $request->description,
        ], 'POST');

        if (! in_array($response['status'] ?? null, [200, 201], true)) {
            throw new RuntimeException('Unable to create Vimeo upload session.');
        }

        $providerVideoId = Str::afterLast((string) data_get($response, 'body.uri'), '/');

        return new CourseVideoUploadSession(
            uploadUrl: (string) data_get($response, 'body.upload.upload_link'),
            method: 'PATCH',
            providerVideoId: $providerVideoId !== '' ? $providerVideoId : null,
            strategy: 'provider_direct',
            metadata: ['response' => $response['body'] ?? []],
        );
    }

    public function importFromUrl(CourseVideoImportRequest $request): CourseVideoImportResult
    {
        $this->ensureVimeoPackageIsInstalled();

        $response = Vimeo::request('/me/videos/', [
            'upload' => [
                'approach' => 'pull',
                'link'     => $request->sourceUrl,
            ],
            'name'        => $request->title,
            'description' => $request->description,
        ], 'POST');

        if (! in_array($response['status'] ?? null, [200, 201], true)) {
            throw new RuntimeException('Unable to import video into Vimeo.');
        }

        $providerVideoId = Str::afterLast((string) data_get($response, 'body.uri'), '/');

        return new CourseVideoImportResult(
            providerVideoId: $providerVideoId,
            link: data_get($response, 'body.link'),
            playerEmbedUrl: data_get($response, 'body.player_embed_url'),
            transcodeStatus: data_get($response, 'body.transcode.status', 'in_progress'),
            duration: data_get($response, 'body.duration'),
            thumbnailUrl: data_get($response, 'body.pictures.sizes.0.link'),
            metadata: ['response' => $response['body'] ?? []],
        );
    }

    public function refreshStatus(string $providerVideoId): CourseVideoStatus
    {
        $this->ensureVimeoPackageIsInstalled();

        $response = Vimeo::request("/videos/{$providerVideoId}", [], 'GET');
        $status = (string) data_get($response, 'body.status', 'unknown');
        $transcodeStatus = data_get($response, 'body.transcode.status');

        return new CourseVideoStatus(
            providerVideoId: $providerVideoId,
            status: $status,
            isReady: $status === 'available' || $transcodeStatus === 'complete',
            transcodeStatus: is_string($transcodeStatus) ? $transcodeStatus : null,
            duration: data_get($response, 'body.duration'),
            thumbnailUrl: data_get($response, 'body.pictures.sizes.0.link'),
            playerEmbedUrl: data_get($response, 'body.player_embed_url'),
            metadata: ['response' => $response['body'] ?? []],
        );
    }

    public function updateVideo(string $providerVideoId, array $properties): void
    {
        $this->ensureVimeoPackageIsInstalled();

        Vimeo::request("/videos/{$providerVideoId}", $properties, 'PATCH');
    }

    public function deleteVideo(string $providerVideoId): void
    {
        $this->ensureVimeoPackageIsInstalled();

        Vimeo::request("/videos/{$providerVideoId}", [], 'DELETE');
    }

    protected function ensureVimeoPackageIsInstalled(): void
    {
        if (! class_exists(Vimeo::class)) {
            throw new RuntimeException('Vimeo provider is configured, but vimeo/laravel is not installed.');
        }
    }
}
