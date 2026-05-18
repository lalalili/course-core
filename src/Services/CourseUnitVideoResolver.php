<?php

namespace Lalalili\CourseCore\Services;

use Illuminate\Database\Eloquent\Model;
use Lalalili\CourseCore\Contracts\CourseVideoPlatformManager;
use Lalalili\CourseCore\Contracts\VideoModelContract;
use Lalalili\CourseCore\Data\CourseUnitVideoPayload;
use Throwable;

class CourseUnitVideoResolver
{
    public function __construct(
        protected readonly CourseVideoPlatformManager $platformManager,
    ) {}

    public function resolve(?Model $unit): CourseUnitVideoPayload
    {
        if (! $unit instanceof Model) {
            return new CourseUnitVideoPayload(null, null, null);
        }

        $video = $unit->getRelationValue('video');

        if ($video instanceof VideoModelContract) {
            return $this->fromVideo($video, $unit);
        }

        return $this->fromLegacyUrl(data_get($unit, 'url'), $unit);
    }

    /**
     * @return array{id: mixed, chapter: mixed, title: mixed, vimeoId: string, videoId: ?string, duration: mixed, videoProvider: ?string, embedUrl: ?string, videoStatus: ?string, transcodeStatus: ?string, videoRecordId: ?int}
     */
    public function navigationPayload(Model $unit): array
    {
        $videoPayload = $this->resolve($unit)->toFrontendArray();

        return [
            'id' => $unit->getKey(),
            'chapter' => data_get($unit, 'parent_id'),
            'title' => data_get($unit, 'title'),
            'vimeoId' => $videoPayload['vimeoId'],
            'videoId' => $videoPayload['videoId'],
            'duration' => data_get($unit, 'duration'),
            'videoProvider' => $videoPayload['videoProvider'],
            'embedUrl' => $videoPayload['embedUrl'],
            'videoStatus' => $videoPayload['videoStatus'],
            'transcodeStatus' => $videoPayload['transcodeStatus'],
            'videoRecordId' => $videoPayload['videoRecordId'],
        ];
    }

    private function fromVideo(VideoModelContract $video, Model $unit): CourseUnitVideoPayload
    {
        $provider = $video->videoProviderKey();
        $providerVideoId = $video->resolvedProviderVideoId();
        $embedUrl = $video->getPlayerEmbedUrl();

        if (! $embedUrl && $providerVideoId) {
            try {
                $embedUrl = $this->platformManager
                    ->provider($provider)
                    ->getEmbedUrl($providerVideoId);
            } catch (Throwable) {
                $embedUrl = null;
            }
        }

        return new CourseUnitVideoPayload(
            videoId: $providerVideoId,
            videoProvider: $provider,
            embedUrl: $embedUrl,
            status: $video->getProviderStatus(),
            transcodeStatus: $video->getTranscodeStatus(),
            duration: $video->getDuration() ?? data_get($unit, 'duration'),
            videoRecordId: (int) $video->getKey(),
            metadata: $video->getVideoMetadata(),
        );
    }

    protected function fromLegacyUrl(?string $url, Model $unit): CourseUnitVideoPayload
    {
        if (! is_string($url) || $url === '') {
            return new CourseUnitVideoPayload(null, null, null, duration: data_get($unit, 'duration'));
        }

        try {
            $platform = $this->platformManager->providerForUrl($url);
            $videoId = $platform->extractVideoId($url);

            return new CourseUnitVideoPayload(
                videoId: $videoId,
                videoProvider: $platform->key(),
                embedUrl: $videoId ? $platform->getEmbedUrl($videoId) : null,
                status: 'legacy_url',
                transcodeStatus: null,
                duration: data_get($unit, 'duration'),
            );
        } catch (Throwable) {
            return new CourseUnitVideoPayload(null, null, null, duration: data_get($unit, 'duration'));
        }
    }
}
