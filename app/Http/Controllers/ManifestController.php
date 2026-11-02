<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Web app manifest (F-43, defect D-29). Generated from `config('app.url')` so
 * the app works in the `/serwis` subdirectory without hard coded paths.
 */
final class ManifestController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $version = (string) config('app.asset_version', '1');
        $name = (string) config('app.name');

        $payload = [
            'name' => $name,
            'short_name' => 'Serwis',
            'description' => 'Serwis laptopów Studio-Komp — kolejka napraw, przyjęcia i wydania.',
            'start_url' => url('/'),
            'scope' => url('/'),
            'display' => 'standalone',
            'orientation' => 'portrait-primary',
            'background_color' => '#f4f6f8',
            'theme_color' => '#0d3b66',
            'lang' => 'pl',
            'icons' => [
                ['src' => asset('icons/icon-96.png').'?v='.$version, 'sizes' => '96x96', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('icons/icon-192.png').'?v='.$version, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('icons/icon-192-maskable.png').'?v='.$version, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'],
                ['src' => asset('icons/icon-512.png').'?v='.$version, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('icons/icon-512-maskable.png').'?v='.$version, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => [
                [
                    'name' => 'Kolejka',
                    'url' => route('services.queue'),
                    'icons' => [['src' => asset('icons/icon-96.png').'?v='.$version, 'sizes' => '96x96']],
                ],
                [
                    'name' => 'Przyjmij laptopa',
                    'url' => route('services.create'),
                    'icons' => [['src' => asset('icons/icon-96.png').'?v='.$version, 'sizes' => '96x96']],
                ],
                [
                    'name' => 'Wyszukaj',
                    'url' => route('services.search'),
                    'icons' => [['src' => asset('icons/icon-96.png').'?v='.$version, 'sizes' => '96x96']],
                ],
            ],
        ];

        return response()
            ->json($payload)
            ->header('Content-Type', 'application/manifest+json');
    }
}
