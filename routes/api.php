<?php

use App\Http\Controllers\Api\V1\ActivationController;
use App\Http\Middleware\ActivationApiContext;
use App\Http\Middleware\AuthenticateInstallation;
use Illuminate\Support\Facades\Route;

Route::get('/v1/status', fn () => response()->json([
    'service' => 'soundmine-platform', 'api_version' => 'v1', 'phase' => 'online-activation-preview',
    'licensing_available' => false,
    'online_activation_available' => (bool) config('soundmine.activations.enabled'),
    'signed_certificates_available' => false, 'offline_authorization_available' => false,
]))->middleware('throttle:60,1');

Route::prefix('/v1/products/{product:slug}')->middleware(ActivationApiContext::class)->group(function () {
    Route::post('/activations', [ActivationController::class, 'store'])->middleware('throttle:activation-exchange')->name('api.v1.activations.store');
    Route::middleware(['throttle:installation-api', AuthenticateInstallation::class])->group(function () {
        Route::get('/entitlements', [ActivationController::class, 'entitlements'])->name('api.v1.entitlements');
        Route::delete('/activations/{activation}', [ActivationController::class, 'destroy'])->whereUuid('activation')->name('api.v1.activations.destroy');
    });
});
