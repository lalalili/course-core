<?php

namespace Lalalili\CourseCore\Contracts;

interface VideoModelContract
{
    public function getKey(): mixed;

    public function videoProviderKey(): string;

    public function resolvedProviderVideoId(): ?string;

    public function getPlayerEmbedUrl(): ?string;

    public function getProviderStatus(): ?string;

    public function getTranscodeStatus(): ?string;

    public function getDuration(): ?int;

    /** @return array<string, mixed> */
    public function getVideoMetadata(): array;
}
