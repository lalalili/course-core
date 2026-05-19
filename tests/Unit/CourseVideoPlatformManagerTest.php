<?php

use Illuminate\Support\Facades\Http;
use Lalalili\CourseCore\Contracts\CourseVideoPlatformManager;
use Lalalili\CourseCore\Data\CourseVideoImportRequest;
use Lalalili\CourseCore\Data\CourseVideoUploadRequest;
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

it('defaults large uploads to the multipart staging strategy', function (): void {
    expect(config('course-core.video_upload_strategy'))->toBe('s3_multipart_then_import');
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

it('creates Cloudflare Stream direct upload sessions', function (): void {
    config()->set('course-core.providers.cloudflare_stream.account_id', 'account-123');
    config()->set('course-core.providers.cloudflare_stream.api_token', 'token-123');

    Http::fake([
        'api.cloudflare.com/client/v4/accounts/account-123/stream/direct_upload' => Http::response([
            'success' => true,
            'result' => [
                'uid' => 'cloudflare-video-1',
                'uploadURL' => 'https://upload.cloudflarestream.com/direct',
            ],
        ]),
    ]);

    $session = app(CloudflareStreamVideoProvider::class)->createDirectUploadSession(
        new CourseVideoUploadRequest(
            fileName: 'intro.mp4',
            fileSize: 1024,
            mimeType: 'video/mp4',
            title: 'Course intro',
            description: 'Welcome video',
            metadata: ['max_duration_seconds' => 3600],
        )
    );

    expect($session?->uploadUrl)->toBe('https://upload.cloudflarestream.com/direct')
        ->and($session?->method)->toBe('POST')
        ->and($session?->providerVideoId)->toBe('cloudflare-video-1')
        ->and($session?->strategy)->toBe('provider_direct');

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer token-123')
        && data_get($request->data(), 'meta.name') === 'Course intro'
        && $request['maxDurationSeconds'] === 3600);
});

it('refreshes, updates, and deletes Cloudflare Stream videos', function (): void {
    config()->set('course-core.providers.cloudflare_stream.account_id', 'account-123');
    config()->set('course-core.providers.cloudflare_stream.api_token', 'token-123');
    config()->set('course-core.providers.cloudflare_stream.customer_subdomain', 'demo');

    Http::fake([
        'api.cloudflare.com/client/v4/accounts/account-123/stream/cloudflare-video-1' => Http::sequence()
            ->push([
                'success' => true,
                'result' => [
                    'uid' => 'cloudflare-video-1',
                    'readyToStream' => true,
                    'duration' => 123000,
                    'status' => ['state' => 'ready'],
                ],
            ])
            ->push(['success' => true])
            ->push(['success' => true]),
    ]);

    $provider = app(CloudflareStreamVideoProvider::class);

    $status = $provider->refreshStatus('cloudflare-video-1');
    $provider->updateVideo('cloudflare-video-1', ['title' => 'Updated title']);
    $provider->deleteVideo('cloudflare-video-1');

    expect($status->isReady)->toBeTrue()
        ->and($status->duration)->toBe(123)
        ->and($status->playerEmbedUrl)->toBe('https://customer-demo.cloudflarestream.com/cloudflare-video-1/iframe');

    Http::assertSent(fn ($request): bool => $request->method() === 'GET'
        && str_ends_with($request->url(), '/stream/cloudflare-video-1'));
    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && data_get($request->data(), 'meta.name') === 'Updated title');
    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE'
        && str_ends_with($request->url(), '/stream/cloudflare-video-1'));
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

it('does not expose VdoCipher direct upload sessions until a host opts into that flow', function (): void {
    $session = app(VdoCipherVideoProvider::class)->createDirectUploadSession(
        new CourseVideoUploadRequest(
            fileName: 'intro.mp4',
            fileSize: 1024,
            mimeType: 'video/mp4',
        )
    );

    expect($session)->toBeNull();
});

it('refreshes, updates, and deletes VdoCipher videos', function (): void {
    config()->set('course-core.providers.vdocipher.api_secret', 'secret-123');

    Http::fake([
        'dev.vdocipher.com/api/videos/vdo-video-1/' => Http::sequence()
            ->push([
                'id' => 'vdo-video-1',
                'status' => 'ready',
                'length' => 321,
                'poster' => 'https://cdn.example.com/poster.jpg',
            ])
            ->push(['status' => 'ok'])
            ->push(['status' => 'ok']),
    ]);

    $provider = app(VdoCipherVideoProvider::class);

    $status = $provider->refreshStatus('vdo-video-1');
    $provider->updateVideo('vdo-video-1', ['title' => 'Updated title']);
    $provider->deleteVideo('vdo-video-1');

    expect($status->isReady)->toBeTrue()
        ->and($status->duration)->toBe(321)
        ->and($status->thumbnailUrl)->toBe('https://cdn.example.com/poster.jpg')
        ->and($status->playerEmbedUrl)->toBe('https://player.vdocipher.com/v2/?videoId=vdo-video-1');

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Apisecret secret-123')
        && $request->method() === 'GET');
    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && $request['title'] === 'Updated title');
    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE');
});

it('extracts Vimeo ids and builds embed urls without host model coupling', function (): void {
    $provider = app(VimeoCourseVideoProvider::class);

    expect($provider->extractVideoId('https://player.vimeo.com/video/123456'))->toBe('123456')
        ->and($provider->extractVideoId('https://vimeo.com/987654'))->toBe('987654')
        ->and($provider->getEmbedUrl('123456'))->toContain('https://player.vimeo.com/video/123456');
});
