<?php

use App\Http\Controllers\PlatformController;
use Illuminate\Support\Facades\Route;

Route::domain(config('tenancy.admin_host'))->prefix('platform')->name('platform.')->group(function (): void {
    Route::get('/login', [PlatformController::class, 'loginForm'])->name('login');
    Route::post('/login', [PlatformController::class, 'login'])->middleware('throttle:login');
    Route::post('/logout', [PlatformController::class, 'logout'])->name('logout');
    Route::get('/security', [PlatformController::class, 'security'])->name('security');
    Route::post('/security', [PlatformController::class, 'verifyMfa'])->middleware('throttle:mfa');
    Route::get('/', [PlatformController::class, 'index'])->name('index');
    Route::post('/tenants/{tenant}/lifecycle', [PlatformController::class, 'lifecycle'])->whereNumber('tenant')->name('lifecycle');
});
