<?php

use App\Http\Controllers\Portal\GoogleAuthController;
use App\Livewire\Portal\AcceptInvitation;
use App\Livewire\Portal\Dashboard;
use App\Livewire\Portal\ForgotPassword;
use App\Livewire\Portal\LicenseDetails;
use App\Livewire\Portal\Login;
use App\Livewire\Portal\ResetPassword;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');
Route::middleware('guest')->group(function () {
    Route::post('/auth/google', [GoogleAuthController::class, 'login'])->middleware('throttle:10,1')->block()->name('google.login');
    Route::post('/invitations/{invitation}/{token}/google', [GoogleAuthController::class, 'invitation'])->whereUuid('invitation')->middleware('throttle:10,1')->block()->name('google.invitation');
    Route::get('/login', Login::class)->name('login');
    Route::get('/invitations/{invitation}/{token}', AcceptInvitation::class)->whereUuid('invitation')->middleware('throttle:10,1')->name('invitation.accept');
    Route::get('/forgot-password', ForgotPassword::class)->name('password.request');
    Route::get('/reset-password/{token}', ResetPassword::class)->name('password.reset');
});
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->middleware(['auth.session', 'throttle:10,1'])->block()->name('google.callback');
Route::middleware(['auth', 'auth.session'])->group(function () {
    Route::view('/email/verify', 'portal.verify-email')->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();

        return redirect()->route('portal');
    })->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', function (Request $request) {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return back()->with('status', 'If verification is needed, a fresh link has been sent.');
    })->middleware('throttle:6,1')->name('verification.send');
    Route::get('/portal/account', [GoogleAuthController::class, 'account'])->middleware('verified')->name('portal.account');
    Route::post('/portal/account/google', [GoogleAuthController::class, 'link'])->middleware(['verified', 'throttle:5,1'])->block()->name('google.link');
    Route::delete('/portal/account/google', [GoogleAuthController::class, 'unlink'])->middleware(['verified', 'throttle:5,1'])->block()->name('google.unlink');
    Route::get('/portal', Dashboard::class)->middleware('verified')->name('portal');
    Route::get('/portal/licenses/{license}', LicenseDetails::class)->whereUuid('license')->middleware('verified')->name('portal.licenses.show');
});
Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');
