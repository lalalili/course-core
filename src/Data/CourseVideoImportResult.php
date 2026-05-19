<?php

namespace Lalalili\CourseCore\Data;

final readonly class CourseVideoImportResult
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $providerVideoId,
        public ?string $link = null,
        public ?string $playerEmbedUrl = null,
        public ?string $transcodeStatus = null,
        public int $status = 0,
        public ?int $duration = null,
        public ?string $thumbnailUrl = null,
        public array $metadata = [],
    ) {}
}
