<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Web app manifest — brand colours and the generated icon set
|--------------------------------------------------------------------------
*/

it('serves the manifest in the brand colours with a real file for every icon size', function () {
    $response = $this->get('/manifest.webmanifest');

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json');

    $payload = $response->json();

    expect($payload['theme_color'])->toBe('#2d343a')
        ->and($payload['background_color'])->toBe('#f4f5f2')
        ->and($payload['icons'])->toHaveCount(5);

    foreach ($payload['icons'] as $icon) {
        $path = parse_url((string) $icon['src'], PHP_URL_PATH);
        $relative = ltrim((string) $path, '/');
        $file = public_path($relative);

        expect(is_file($file))->toBeTrue('Ikona '.$relative.' nie istnieje');

        $size = getimagesize($file);
        $actual = $size === false ? 'nieczytelny PNG' : sprintf('%dx%d', $size[0], $size[1]);

        expect($actual)->toBe((string) $icon['sizes'], 'Zły rozmiar ikony '.$relative);
    }
});
