<?php

use App\Models\Post;
use App\Models\PostTarget;
use App\Services\Posts\PlatformPostFieldsService;

it('builds facebook photo payload with caption and published flag only', function () {
    $service = new PlatformPostFieldsService();
    $target = new PostTarget([]);
    $target->setRelation('post', new Post([
        'caption' => 'Hello world',
        'hashtags' => ['#test'],
    ]));

    $payload = $service->buildFacebookApiPayload($target, true);

    expect($payload['caption'])->toContain('Hello world')
        ->and($payload['caption'])->toContain('#test')
        ->and($payload['published'])->toBeTrue()
        ->and($payload)->not->toHaveKey('link')
        ->and($payload)->not->toHaveKey('name');
});

it('builds pinterest payload with title description and optional link', function () {
    $service = new PlatformPostFieldsService();
    $target = new PostTarget([
        'platform_payload' => [
            'title' => 'Summer Sale',
            'link_url' => 'https://example.com',
        ],
    ]);
    $target->setRelation('post', new Post([
        'title' => 'Summer Sale',
        'caption' => 'Pin description',
        'hashtags' => ['#sale'],
    ]));

    $payload = $service->buildPinterestApiPayload($target, ['source_type' => 'image_url', 'url' => 'https://img.test/pin.jpg']);

    expect($payload['title'])->toBe('Summer Sale')
        ->and($payload['description'])->toContain('Pin description')
        ->and($payload['description'])->toContain('#sale')
        ->and($payload['link'])->toBe('https://example.com');
});
