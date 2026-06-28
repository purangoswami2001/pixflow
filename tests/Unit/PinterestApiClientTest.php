<?php

use App\Services\Social\PinterestApiClient;

it('includes user_accounts read scope in pinterest config', function () {
    $config = require dirname(__DIR__, 2) . '/config/social-platforms.php';
    $scopes = $config['platforms']['pinterest']['scopes'];

    expect($scopes)->toContain('user_accounts:read');
    expect($scopes)->toContain('boards:write');
    expect($scopes)->toContain('pins:write');
});

it('parses granted pinterest scopes from token response', function () {
    $scopes = PinterestApiClient::parseGrantedScopes('user_accounts:read,boards:read,pins:write');

    expect($scopes)->toBe(['user_accounts:read', 'boards:read', 'pins:write']);
});

it('uses sandbox api url when configured', function () {
    expect(PinterestApiClient::SANDBOX_API_URL)->toBe('https://api-sandbox.pinterest.com');
});

it('maps pin image content types to jpeg or png', function () {
    expect(PinterestApiClient::pinImageContentType('image/png'))->toBe('image/png');
    expect(PinterestApiClient::pinImageContentType('image/jpeg'))->toBe('image/jpeg');
    expect(PinterestApiClient::pinImageContentType('image/webp'))->toBe('image/jpeg');
});

it('redacts base64 image data from log payloads', function () {
    $redacted = PinterestApiClient::redactPayloadForLog([
        'media_source' => [
            'source_type' => 'image_base64',
            'data' => base64_encode('test'),
        ],
    ]);

    expect($redacted['media_source']['data'])->toBe('[base64 8 chars]');
});

it('detects non public image urls', function () {
    expect(PinterestApiClient::isPubliclyAccessibleUrl('https://cdn.example.com/image.jpg'))->toBeTrue();
    expect(PinterestApiClient::isPubliclyAccessibleUrl('https://foo.ngrok-free.app/image.jpg'))->toBeFalse();
    expect(PinterestApiClient::isPubliclyAccessibleUrl('http://localhost/image.jpg'))->toBeFalse();
});
