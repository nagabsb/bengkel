<?php

use App\Services\Platform\PlatformAiAgentService;

test('it maps invalid api key error to user friendly message', function () {
    $service = new PlatformAiAgentService();

    $method = new ReflectionMethod(PlatformAiAgentService::class, 'sanitizeExceptionMessage');
    $method->setAccessible(true);

    $message = $method->invoke(
        $service,
        'gemini',
        'Gemini Error [400]: INVALID_ARGUMENT - API key not valid. Please pass a valid API key.',
    );

    expect($message)->toBe('API key Google Gemini tidak valid. Periksa kembali key dari dashboard Google Gemini, lalu simpan ulang.');
});
