<?php

use Illuminate\Support\Facades\Route;

// When the compiled frontend lives inside public/ (desktop package), Laravel serves the SPA
$spa_index = public_path('index.html');

Route::get('/', function () use ($spa_index) {
    if (file_exists($spa_index)) {
        return response()->file($spa_index, ['Cache-Control' => 'no-cache, no-store, must-revalidate']);
    }

    return view('welcome');
});

Route::fallback(function () use ($spa_index) {
    abort_if(request()->is('api/*') || ! file_exists($spa_index), 404);

    return response()->file($spa_index, ['Cache-Control' => 'no-cache, no-store, must-revalidate']);
});
