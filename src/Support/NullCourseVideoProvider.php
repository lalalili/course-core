<?php

namespace Lalalili\CourseCore\Support;

use Lalalili\CourseCore\Contracts\CourseVideoPlatform;
use Lalalili\CourseCore\Data\CourseVideoDetails;
use Lalalili\CourseCore\Data\CourseVideoImportRequest;
use Lalalili\CourseCore\Data\CourseVideoImportResult;
use Lalalili\CourseCore\Data\CourseVideoStatus;
use Lalalili\CourseCore\Data\CourseVideoUploadRequest;
use Lalalili\CourseCore\Data\CourseVideoUploadSession;
use RuntimeException;

class NullCourseVideoProvider implements CourseVideoPlatform
{
    public function key(): string
    {
        return 'null';
    }

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

    public function createDirectUploadSession(CourseVideoUploadRequest $request): ?CourseVideoUploadSession
    {
        return null;
    }

    public function importFromUrl(CourseVideoImportRequest $request): CourseVideoImportResult
    {
        throw new RuntimeException('No course video platform is configured.');
    }

    public function refreshStatus(string $providerVideoId): CourseVideoStatus
    {
        return new CourseVideoStatus(
            providerVideoId: $providerVideoId,
            status: 'missing_provider',
            isReady: false,
        );
    }

    public function updateVideo(string $providerVideoId, array $properties): void
    {
    }

    public function deleteVideo(string $providerVideoId): void
    {
    }
}
