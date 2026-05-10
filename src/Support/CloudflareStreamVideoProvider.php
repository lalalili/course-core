<?php

namespace Lalalili\CourseCore\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Lalalili\CourseCore\Contracts\CourseVideoPlatform;
use Lalalili\CourseCore\Data\CourseVideoDetails;
use Lalalili\CourseCore\Data\CourseVideoImportRequest;
use Lalalili\CourseCore\Data\CourseVideoImportResult;
use Lalalili\CourseCore\Data\CourseVideoStatus;
use Lalalili\CourseCore\Data\CourseVideoUploadRequest;
use Lalalili\CourseCore\Data\CourseVideoUploadSession;
use RuntimeException;

class CloudflareStreamVideoProvider implements CourseVideoPlatform
{
    public function key(): string
    {
        return 'cloudflare_stream';
    }

    public function extractVideoId(?string $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        if (preg_match('#(?:videodelivery\.net|cloudflarestream\.com)/([A-Za-z0-9_-]+)#', $url, $matches)) {
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
        $customerSubdomain = config('course-core.providers.cloudflare_stream.customer_subdomain');

        if (is_string($customerSubdomain) && $customerSubdomain !== '') {
            return "https://customer-{$customerSubdomain}.cloudflarestream.com/{$videoId}/iframe";
        }

        return "https://iframe.videodelivery.net/{$videoId}";
    }

    public function createDirectUploadSession(CourseVideoUploadRequest $request): ?CourseVideoUploadSession
    {
        $response = $this->client()
            ->asJson()
            ->post($this->endpoint('stream/direct_upload'), [
                'maxDurationSeconds' => $request->metadata['max_duration_seconds'] ?? null,
                'meta'               => array_filter([
                    'name'        => $request->title ?? pathinfo($request->fileName, PATHINFO_FILENAME),
                    'description' => $request->description,
                ]),
            ])
            ->throw()
            ->json();

        return new CourseVideoUploadSession(
            uploadUrl: (string) data_get($response, 'result.uploadURL'),
            method: 'POST',
            providerVideoId: data_get($response, 'result.uid'),
            strategy: 'provider_direct',
            metadata: ['response' => $response['result'] ?? []],
        );
    }

    public function importFromUrl(CourseVideoImportRequest $request): CourseVideoImportResult
    {
        $response = $this->client()
            ->asJson()
            ->post($this->endpoint('stream/copy'), [
                'url'  => $request->sourceUrl,
                'meta' => array_filter([
                    'name'        => $request->title,
                    'description' => $request->description,
                ]),
            ])
            ->throw()
            ->json();

        $uid = (string) data_get($response, 'result.uid');

        return new CourseVideoImportResult(
            providerVideoId: $uid,
            link: "https://watch.videodelivery.net/{$uid}",
            playerEmbedUrl: $this->getEmbedUrl($uid),
            transcodeStatus: (string) data_get($response, 'result.status.state', 'inprogress'),
            duration: $this->secondsFromMilliseconds(data_get($response, 'result.duration')),
            thumbnailUrl: "https://videodelivery.net/{$uid}/thumbnails/thumbnail.jpg",
            metadata: ['response' => $response['result'] ?? []],
        );
    }

    public function refreshStatus(string $providerVideoId): CourseVideoStatus
    {
        $response = $this->client()
            ->get($this->endpoint("stream/{$providerVideoId}"))
            ->throw()
            ->json();

        $state = (string) data_get($response, 'result.status.state', 'unknown');
        $ready = (bool) data_get($response, 'result.readyToStream', false);

        return new CourseVideoStatus(
            providerVideoId: $providerVideoId,
            status: $state,
            isReady: $ready || $state === 'ready',
            transcodeStatus: $state,
            duration: $this->secondsFromMilliseconds(data_get($response, 'result.duration')),
            thumbnailUrl: "https://videodelivery.net/{$providerVideoId}/thumbnails/thumbnail.jpg",
            playerEmbedUrl: $this->getEmbedUrl($providerVideoId),
            metadata: ['response' => $response['result'] ?? []],
        );
    }

    public function updateVideo(string $providerVideoId, array $properties): void
    {
        $this->client()
            ->asJson()
            ->post($this->endpoint("stream/{$providerVideoId}"), [
                'meta' => array_filter([
                    'name'        => $properties['name'] ?? $properties['title'] ?? null,
                    'description' => $properties['description'] ?? null,
                ]),
            ])
            ->throw();
    }

    public function deleteVideo(string $providerVideoId): void
    {
        $this->client()
            ->delete($this->endpoint("stream/{$providerVideoId}"))
            ->throw();
    }

    protected function client(): PendingRequest
    {
        $token = config('course-core.providers.cloudflare_stream.api_token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Cloudflare Stream API token is not configured.');
        }

        return Http::withToken($token)->timeout(30)->connectTimeout(10);
    }

    protected function endpoint(string $path): string
    {
        $accountId = config('course-core.providers.cloudflare_stream.account_id');

        if (! is_string($accountId) || $accountId === '') {
            throw new RuntimeException('Cloudflare Stream account ID is not configured.');
        }

        return 'https://api.cloudflare.com/client/v4/accounts/'.Str::of($accountId)->trim('/').'/'.ltrim($path, '/');
    }

    protected function secondsFromMilliseconds(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        return (int) ceil(((float) $value) / 1000);
    }
}
