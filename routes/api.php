<?php

use Illuminate\Support\Facades\Route;

Route::get('/v1/status', fn () => response()->json([
    'service' => 'soundmine-platform',
    'api_version' => 'v1',
    'phase' => 'foundation',
    'licensing_available' => false,
]))->middleware('throttle:60,1');
