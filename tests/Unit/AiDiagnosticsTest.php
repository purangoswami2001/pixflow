<?php

use App\Support\AiDiagnostics;

uses(Tests\TestCase::class);

test('ai diagnostics redact provider details outside debug mode', function () {
    config(['app.debug' => false]);

    $result = AiDiagnostics::forDisplay([
        'step' => 'copy',
        'provider' => 'cursor',
        'text_provider' => 'cursor',
        'nested' => [
            '_provider' => 'gemini',
            'model' => 'gemini-2.5-flash',
            'message' => 'Cursor Cloud API request failed.',
        ],
    ]);

    expect($result)->toBe([
        'step' => 'copy',
        'nested' => [
            'message' => 'AI provider request failed.',
        ],
    ]);
});

test('ai diagnostics preserve provider details in debug mode', function () {
    config(['app.debug' => true]);

    $diagnostics = [
        'provider' => 'cursor',
        'model' => 'auto',
        'message' => 'Cursor API request failed.',
    ];

    expect(AiDiagnostics::forDisplay($diagnostics))->toBe($diagnostics);
});
