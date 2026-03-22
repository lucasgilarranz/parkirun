<?php

use Illuminate\Support\Facades\Route;

Route::get('/manifest.webmanifest', function (): \Illuminate\Http\Response {
    $manifest = [
        'name' => config('app.name'),
        'short_name' => config('app.name'),
        'start_url' => '/',
        'display' => 'standalone',
        'background_color' => '#fafafa',
        'theme_color' => '#171717',
        'icons' => [
            [
                'src' => '/apple-touch-icon.png',
                'sizes' => '180x180',
                'type' => 'image/png',
                'purpose' => 'any',
            ],
        ],
    ];

    return response(
        json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        200,
        ['Content-Type' => 'application/manifest+json']
    );
})->name('pwa.manifest');

Route::livewire('/', 'pages::home')->name('home');

Route::livewire('about', 'pages::about')->name('about');

Route::livewire('dashboard', 'pages::dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

require __DIR__.'/settings.php';
