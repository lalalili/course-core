<?php

namespace Lalalili\CourseCore\Data;

readonly class CourseUnitVideoPayload
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public ?string $videoId,
        public ?string $videoProvider,
        public ?string $embedUrl,
        public ?string $status = null,
        public ?string $transcodeStatus = null,
        public ?int $duration = null,
        public ?int $videoRecordId = null,
        public array $metadata = [],
    ) {}

    /**
     * @return array{
     *     vimeoId: string,
     *     videoId: ?string,
     *     videoProvider: ?string,
     *     embedUrl: ?string,
     *     videoStatus: ?string,
     *     transcodeStatus: ?string,
     *     videoRecordId: ?int
     * }
     */
    public function toFrontendArray(): array
    {
        return [
            'vimeoId' => $this->videoId ?: '無法提取影片 ID',
            'videoId' => $this->videoId,
            'videoProvider' => $this->videoProvider,
            'embedUrl' => $this->embedUrl,
            'videoStatus' => $this->status,
            'transcodeStatus' => $this->transcodeStatus,
            'videoRecordId' => $this->videoRecordId,
        ];
    }
}
