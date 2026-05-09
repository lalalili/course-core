<?php

namespace Lalalili\CourseCore\Data;

final readonly class CourseVideoReference
{
    public function __construct(
        public string $provider,
        public string $videoId,
        public ?string $originalUrl = null,
    ) {}
}
