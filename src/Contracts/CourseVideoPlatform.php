<?php

namespace Lalalili\CourseCore\Contracts;

use Lalalili\CourseCore\Data\CourseVideoImportRequest;
use Lalalili\CourseCore\Data\CourseVideoImportResult;
use Lalalili\CourseCore\Data\CourseVideoStatus;
use Lalalili\CourseCore\Data\CourseVideoUploadRequest;
use Lalalili\CourseCore\Data\CourseVideoUploadSession;

interface CourseVideoPlatform extends CourseVideoProvider
{
    public function key(): string;

    public function createDirectUploadSession(CourseVideoUploadRequest $request): ?CourseVideoUploadSession;

    public function importFromUrl(CourseVideoImportRequest $request): CourseVideoImportResult;

    public function refreshStatus(string $providerVideoId): CourseVideoStatus;

    /**
     * @param  array<string, mixed>  $properties
     */
    public function updateVideo(string $providerVideoId, array $properties): void;

    public function deleteVideo(string $providerVideoId): void;
}
