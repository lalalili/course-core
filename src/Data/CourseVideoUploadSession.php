<?php

namespace Lalalili\CourseCore\Data;

final readonly class CourseVideoUploadSession
{
    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $uploadUrl,
        public string $method = 'PUT',
        public array $headers = [],
        public ?string $providerVideoId = null,
        public ?string $stagingDisk = null,
        public ?string $stagingPath = null,
        public string $strategy = 'provider_direct',
        public array $metadata = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'upload_url' => $this->uploadUrl,
            'method' => $this->method,
            'headers' => $this->headers,
            'provider_video_id' => $this->providerVideoId,
            'staging_disk' => $this->stagingDisk,
            'staging_path' => $this->stagingPath,
            'strategy' => $this->strategy,
            'metadata' => $this->metadata,
        ];
    }
}
