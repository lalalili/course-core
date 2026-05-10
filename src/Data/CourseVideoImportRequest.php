<?php

namespace Lalalili\CourseCore\Data;

use Illuminate\Contracts\Auth\Authenticatable;

final readonly class CourseVideoImportRequest
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $sourceUrl,
        public ?string $title = null,
        public ?string $description = null,
        public ?int $folderId = null,
        public ?Authenticatable $creator = null,
        public array $metadata = [],
    ) {
    }
}
