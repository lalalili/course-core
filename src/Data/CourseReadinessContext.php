<?php

namespace Lalalili\CourseCore\Data;

final readonly class CourseReadinessContext
{
    public function __construct(
        public bool $requireProduct = false,
        public bool $requireReadyVideos = false,
    ) {}
}
