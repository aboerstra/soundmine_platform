<?php

use App\Livewire\Portal\Dashboard;
use App\Livewire\Portal\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');
Route::get('/login', Login::class)->middleware('guest')->name('login');
Route::get('/portal', Dashboard::class)->middleware('auth')->name('portal');
Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/login');
})->middleware('auth')->name('logout');
