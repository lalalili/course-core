<?php

namespace Lalalili\CourseCore\Data;

final readonly class CourseVideoStatus
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $providerVideoId,
        public string $status,
        public bool $isReady = false,
        public ?string $transcodeStatus = null,
        public ?int $duration = null,
        public ?string $thumbnailUrl = null,
        public ?string $playerEmbedUrl = null,
        public array $metadata = [],
    ) {}
}
