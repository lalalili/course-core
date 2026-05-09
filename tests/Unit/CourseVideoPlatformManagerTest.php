<?php

use Illuminate\Support\Facades\Http;
use Lalalili\CourseCore\Contracts\CourseVideoPlatformManager;
use Lalalili\CourseCore\Data\CourseVideoImportRequest;
use Lalalili\CourseCore\Support\CloudflareStreamVideoProvider;
use Lalalili\CourseCore\Support\VdoCipherVideoProvider;
use Lalalili\CourseCore\Support\VimeoCourseVideoProvider;

it('resolves configured video platforms by key and url', function (): void {
    config()->set('course-core.default_video_platform', 'cloudflare_stream');

    $manager = app(CourseVideoPlatformManager::class);

    expect($manager->provider())->toBeInstanceOf(CloudflareStreamVideoProvider::class)
        ->and($manager->provider('vimeo'))->toBeInstanceOf(VimeoCourseVideoProvider::class)
        ->and($manager->providerForUrl('https://player.vimeo.com/video/123456'))->toBeInstanceOf(VimeoCourseVideoProvider::class)
        ->and($manager->providerForUrl('https://iframe.videodelivery.net/abc123'))->toBeInstanceOf(CloudflareStreamVideoProvider::class);
});

it('imports Cloudflare Stream videos from a source url', function (): void {
    config()->set('course-core.providers.cloudflare_stream.account_id', 'account-123');
    config()->set('course-core.providers.cloudflare_stream.api_token', 'token-123');

    Http::fake([
        'api.cloudflare.com/client/v4/accounts/account-123/stream/copy' => Http::response([
            'success' => true,
            'result' => [
                'uid' => 'cloudflare-video-1',
                'readyToStream' => false,
                'duration' => 123000,
                'status' => ['state' => 'inprogress'],
            ],
        ]),
    ]);

    $result = app(CloudflareStreamVideoProvider::class)->importFromUrl(
        new CourseVideoImportRequest(
            sourceUrl: 'https://storage.example.com/video.mp4',
            title: 'Course intro',
        )
    );

    expect($result->providerVideoId)->toBe('cloudflare-video-1')
        ->and($result->playerEmbedUrl)->toBe('https://iframe.videodelivery.net/cloudflare-video-1')
        ->and($result->duration)->toBe(123);

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer token-123')
        && $request['url'] === 'https://storage.example.com/video.mp4'
        && data_get($request->data(), 'meta.name') === 'Course intro');
});

it('imports VdoCipher videos from a source url', function (): void {
    config()->set('course-core.providers.vdocipher.api_secret', 'secret-123');

    Http::fake([
        'dev.vdocipher.com/api/videos/importUrl' => Http::response([
            'id' => 'vdo-video-1',
            'status' => 'queued',
        ]),
    ]);

    $result = app(VdoCipherVideoProvider::class)->importFromUrl(
        new CourseVideoImportRequest(
            sourceUrl: 'https://storage.example.com/video.mp4',
            title: 'Course intro',
        )
    );

    expect($result->providerVideoId)->toBe('vdo-video-1')
        ->and($result->playerEmbedUrl)->toBe('https://player.vdocipher.com/v2/?videoId=vdo-video-1')
        ->and($result->transcodeStatus)->toBe('queued');

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Apisecret secret-123')
        && $request['url'] === 'https://storage.example.com/video.mp4'
        && $request['folderId'] === 'root');
});
