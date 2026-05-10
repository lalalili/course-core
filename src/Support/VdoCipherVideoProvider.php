<?php

namespace Lalalili\CourseCore\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Lalalili\CourseCore\Contracts\CourseVideoPlatform;
use Lalalili\CourseCore\Data\CourseVideoDetails;
use Lalalili\CourseCore\Data\CourseVideoImportRequest;
use Lalalili\CourseCore\Data\CourseVideoImportResult;
use Lalalili\CourseCore\Data\CourseVideoStatus;
use Lalalili\CourseCore\Data\CourseVideoUploadRequest;
use Lalalili\CourseCore\Data\CourseVideoUploadSession;
use RuntimeException;

class VdoCipherVideoProvider implements CourseVideoPlatform
{
    public function key(): string
    {
        return 'vdocipher';
    }

    public function extractVideoId(?string $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        if (preg_match('#videos/([A-Za-z0-9_-]+)#', $url, $matches)) {
            return $matches[1];
        }

        if (preg_match('#videoId=([A-Za-z0-9_-]+)#', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function getVideoDetails(string $videoId): ?CourseVideoDetails
    {
        $status = $this->refreshStatus($videoId);

        if (! $status->duration || ! $status->playerEmbedUrl) {
            return null;
        }

        return new CourseVideoDetails(
            duration: $status->duration,
            playerEmbedUrl: $status->playerEmbedUrl,
        );
    }

    public function getEmbedUrl(string $videoId, array $options = []): string
    {
        return "https://player.vdocipher.com/v2/?videoId={$videoId}";
    }

    public function createDirectUploadSession(CourseVideoUploadRequest $request): ?CourseVideoUploadSession
    {
        return null;
    }

    public function importFromUrl(CourseVideoImportRequest $request): CourseVideoImportResult
    {
        $response = $this->client()
            ->asJson()
            ->put('https://dev.vdocipher.com/api/videos/importUrl', array_filter([
                'url'      => $request->sourceUrl,
                'folderId' => $request->metadata['folder_id'] ?? 'root',
                'title'    => $request->title,
            ]))
            ->throw()
            ->json();

        $videoId = (string) (data_get($response, 'id') ?? data_get($response, 'videoId'));

        return new CourseVideoImportResult(
            providerVideoId: $videoId,
            link: "https://dashboard.vdocipher.com/videos/{$videoId}",
            playerEmbedUrl: $this->getEmbedUrl($videoId),
            transcodeStatus: (string) data_get($response, 'status', 'queued'),
            metadata: ['response' => $response],
        );
    }

    public function refreshStatus(string $providerVideoId): CourseVideoStatus
    {
        $response = $this->client()
            ->get("https://dev.vdocipher.com/api/videos/{$providerVideoId}/")
            ->throw()
            ->json();

        $status = (string) data_get($response, 'status', 'unknown');

        return new CourseVideoStatus(
            providerVideoId: $providerVideoId,
            status: $status,
            isReady: in_array($status, ['ready', 'complete', 'available'], true),
            transcodeStatus: $status,
            duration: data_get($response, 'length'),
            thumbnailUrl: data_get($response, 'poster'),
            playerEmbedUrl: $this->getEmbedUrl($providerVideoId),
            metadata: ['response' => $response],
        );
    }

    public function updateVideo(string $providerVideoId, array $properties): void
    {
        $this->client()
            ->asJson()
            ->post("https://dev.vdocipher.com/api/videos/{$providerVideoId}/", array_filter([
                'title'       => $properties['title'] ?? $properties['name'] ?? null,
                'description' => $properties['description'] ?? null,
            ]))
            ->throw();
    }

    public function deleteVideo(string $providerVideoId): void
    {
        $this->client()
            ->delete("https://dev.vdocipher.com/api/videos/{$providerVideoId}/")
            ->throw();
    }

    protected function client(): PendingRequest
    {
        $apiSecret = config('course-core.providers.vdocipher.api_secret');

        if (! is_string($apiSecret) || $apiSecret === '') {
            throw new RuntimeException('VdoCipher API secret is not configured.');
        }

        return Http::withHeaders([
            'Authorization' => "Apisecret {$apiSecret}",
            'Accept'        => 'application/json',
        ])->timeout(30)->connectTimeout(10);
    }
}
