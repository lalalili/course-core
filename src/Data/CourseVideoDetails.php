<?php

namespace Lalalili\CourseCore\Data;

final readonly class CourseVideoDetails
{
    public function __construct(
        public int $duration,
        public string $playerEmbedUrl,
    ) {
    }

    /**
     * @return array{duration:int, player_embed_url:string}
     */
    public function toArray(): array
    {
        return [
            'duration'         => $this->duration,
            'player_embed_url' => $this->playerEmbedUrl,
        ];
    }
}
