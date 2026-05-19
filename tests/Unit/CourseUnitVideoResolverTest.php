<?php

use Illuminate\Database\Eloquent\Model;
use Lalalili\CourseCore\Contracts\CourseVideoPlatform;
use Lalalili\CourseCore\Contracts\CourseVideoPlatformManager;
use Lalalili\CourseCore\Contracts\VideoModelContract;
use Lalalili\CourseCore\Data\CourseUnitVideoPayload;
use Lalalili\CourseCore\Data\CourseVideoDetails;
use Lalalili\CourseCore\Data\CourseVideoImportRequest;
use Lalalili\CourseCore\Data\CourseVideoImportResult;
use Lalalili\CourseCore\Data\CourseVideoStatus;
use Lalalili\CourseCore\Data\CourseVideoUploadRequest;
use Lalalili\CourseCore\Data\CourseVideoUploadSession;
use Lalalili\CourseCore\Services\CourseUnitVideoResolver;

// ---------------------------------------------------------------------------
// Stubs
// ---------------------------------------------------------------------------

function makeUnit(array $attrs = []): Model
{
    $unit = new class extends Model
    {
        public $timestamps = false;

        protected $guarded = [];
    };

    foreach ($attrs as $k => $v) {
        $unit->setAttribute($k, $v);
    }

    return $unit;
}

function makeVideo(array $attrs = []): VideoModelContract
{
    return new class($attrs) implements VideoModelContract
    {
        public function __construct(private array $data) {}

        public function videoProviderKey(): string
        {
            return $this->data['providerKey'] ?? 'vimeo';
        }

        public function resolvedProviderVideoId(): ?string
        {
            return $this->data['providerVideoId'] ?? null;
        }

        public function getPlayerEmbedUrl(): ?string
        {
            return $this->data['playerEmbedUrl'] ?? null;
        }

        public function getProviderStatus(): ?string
        {
            return $this->data['providerStatus'] ?? null;
        }

        public function getTranscodeStatus(): ?string
        {
            return $this->data['transcodeStatus'] ?? null;
        }

        public function getDuration(): ?int
        {
            return $this->data['duration'] ?? null;
        }

        public function getVideoMetadata(): array
        {
            return $this->data['metadata'] ?? [];
        }

        public function getKey(): mixed
        {
            return $this->data['id'] ?? 1;
        }
    };
}

function makeManager(?string $embedUrlForId = null): CourseVideoPlatformManager
{
    return new class($embedUrlForId) implements CourseVideoPlatformManager
    {
        public function __construct(private ?string $embedUrl) {}

        public function defaultProviderKey(): string
        {
            return 'vimeo';
        }

        public function provider(?string $provider = null): CourseVideoPlatform
        {
            return $this->makePlatform($this->embedUrl);
        }

        public function providerForUrl(?string $url): CourseVideoPlatform
        {
            return $this->makePlatform($this->embedUrl);
        }

        private function makePlatform(?string $embed): CourseVideoPlatform
        {
            return new class($embed) implements CourseVideoPlatform
            {
                public function __construct(private ?string $embed) {}

                public function key(): string
                {
                    return 'vimeo';
                }

                public function getEmbedUrl(string $videoId, array $options = []): string
                {
                    return $this->embed ?? "https://player.vimeo.com/video/{$videoId}";
                }

                public function extractVideoId(?string $url): ?string
                {
                    if ($url === null) {
                        return null;
                    }

                    preg_match('#/(\d+)#', $url, $m);

                    return $m[1] ?? null;
                }

                public function getVideoDetails(string $videoId): ?CourseVideoDetails
                {
                    return null;
                }

                public function createDirectUploadSession(CourseVideoUploadRequest $request): ?CourseVideoUploadSession
                {
                    return null;
                }

                public function importFromUrl(CourseVideoImportRequest $request): CourseVideoImportResult
                {
                    throw new BadMethodCallException('not used in tests');
                }

                public function refreshStatus(string $providerVideoId): CourseVideoStatus
                {
                    throw new BadMethodCallException('not used in tests');
                }

                public function updateVideo(string $providerVideoId, array $properties): void {}

                public function deleteVideo(string $providerVideoId): void {}
            };
        }
    };
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('resolve returns empty payload for null unit', function (): void {
    $resolver = new CourseUnitVideoResolver(makeManager());
    $payload = $resolver->resolve(null);

    expect($payload)->toBeInstanceOf(CourseUnitVideoPayload::class)
        ->and($payload->videoId)->toBeNull()
        ->and($payload->videoProvider)->toBeNull();
});

it('resolve uses VideoModelContract when set as relation value', function (): void {
    $video = makeVideo(['providerKey' => 'vimeo', 'providerVideoId' => '12345', 'id' => 7]);
    $unit = makeUnit();
    $unit->setRelation('video', $video);

    $resolver = new CourseUnitVideoResolver(makeManager('https://embed/12345'));
    $payload = $resolver->resolve($unit);

    expect($payload->videoId)->toBe('12345')
        ->and($payload->videoProvider)->toBe('vimeo')
        ->and($payload->videoRecordId)->toBe(7);
});

it('supports Eloquent video models without overriding getKey', function (): void {
    $video = new class extends Model implements VideoModelContract
    {
        public function videoProviderKey(): string
        {
            return 'vimeo';
        }

        public function resolvedProviderVideoId(): ?string
        {
            return '12345';
        }

        public function getPlayerEmbedUrl(): ?string
        {
            return 'https://embed/12345';
        }

        public function getProviderStatus(): ?string
        {
            return 'ready';
        }

        public function getTranscodeStatus(): ?string
        {
            return 'complete';
        }

        public function getDuration(): ?int
        {
            return 120;
        }

        public function getVideoMetadata(): array
        {
            return [];
        }
    };

    $video->setAttribute($video->getKeyName(), 8);
    $unit = makeUnit();
    $unit->setRelation('video', $video);

    $payload = (new CourseUnitVideoResolver(makeManager()))->resolve($unit);

    expect($payload->videoRecordId)->toBe(8)
        ->and($payload->videoId)->toBe('12345');
});

it('fromVideo uses playerEmbedUrl when already set (no manager call needed)', function (): void {
    $video = makeVideo([
        'providerKey' => 'vimeo',
        'providerVideoId' => 'abc',
        'playerEmbedUrl' => 'https://cached-embed/abc',
    ]);
    $unit = makeUnit();
    $unit->setRelation('video', $video);

    $throwingManager = new class implements CourseVideoPlatformManager
    {
        public function defaultProviderKey(): string
        {
            return 'vimeo';
        }

        public function provider(?string $provider = null): CourseVideoPlatform
        {
            throw new RuntimeException('should not be called');
        }

        public function providerForUrl(?string $url): CourseVideoPlatform
        {
            throw new RuntimeException('should not be called');
        }
    };

    $resolver = new CourseUnitVideoResolver($throwingManager);
    $payload = $resolver->resolve($unit);

    expect($payload->embedUrl)->toBe('https://cached-embed/abc');
});

it('falls back to legacy URL when unit has no video_id', function (): void {
    $unit = makeUnit(['url' => 'https://vimeo.com/99999', 'duration' => 120]);

    $resolver = new CourseUnitVideoResolver(makeManager());
    $payload = $resolver->resolve($unit);

    expect($payload->videoId)->toBe('99999')
        ->and($payload->status)->toBe('legacy_url')
        ->and($payload->duration)->toBe(120);
});

it('returns empty payload for unit with empty url and no video', function (): void {
    $unit = makeUnit(['url' => null, 'duration' => 60]);

    $resolver = new CourseUnitVideoResolver(makeManager());
    $payload = $resolver->resolve($unit);

    expect($payload->videoId)->toBeNull()
        ->and($payload->duration)->toBe(60);
});

it('navigationPayload includes unit id, parent_id, title and video fields', function (): void {
    $video = makeVideo(['providerKey' => 'vimeo', 'providerVideoId' => 'nav123', 'id' => 5]);
    $unit = makeUnit(['parent_id' => 10, 'title' => 'Lesson 1', 'duration' => 300]);
    $unit->setAttribute($unit->getKeyName(), 42);
    $unit->setRelation('video', $video);

    $resolver = new CourseUnitVideoResolver(makeManager());
    $nav = $resolver->navigationPayload($unit);

    expect($nav['id'])->toBe(42)
        ->and($nav['chapter'])->toBe(10)
        ->and($nav['title'])->toBe('Lesson 1')
        ->and($nav['videoId'])->toBe('nav123')
        ->and($nav['duration'])->toBe(300);
});

it('CourseUnitVideoPayload toFrontendArray returns correct shape', function (): void {
    $payload = new CourseUnitVideoPayload(
        videoId: 'xyz',
        videoProvider: 'cloudflare_stream',
        embedUrl: 'https://embed.example.com/xyz',
        status: 'ready',
        transcodeStatus: 'complete',
        videoRecordId: 99,
    );

    $arr = $payload->toFrontendArray();

    expect($arr['vimeoId'])->toBe('xyz')
        ->and($arr['videoId'])->toBe('xyz')
        ->and($arr['videoProvider'])->toBe('cloudflare_stream')
        ->and($arr['embedUrl'])->toBe('https://embed.example.com/xyz')
        ->and($arr['videoStatus'])->toBe('ready')
        ->and($arr['transcodeStatus'])->toBe('complete')
        ->and($arr['videoRecordId'])->toBe(99);
});

it('toFrontendArray vimeoId fallback when videoId is null', function (): void {
    $payload = new CourseUnitVideoPayload(null, null, null);

    expect($payload->toFrontendArray()['vimeoId'])->toBe('無法提取影片 ID');
});
