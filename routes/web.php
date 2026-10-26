<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CareAssignmentController;
use App\Http\Controllers\ClinicalController;
use App\Http\Controllers\ClinicSettingsController;
use App\Http\Controllers\CommerceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/platform.php';

Route::get('/', [OnboardingController::class, 'landing']);
Route::get('/onboarding', [OnboardingController::class, 'clinicForm']);
Route::post('/onboarding', [OnboardingController::class, 'clinic'])->middleware('throttle:login');
Route::post('/webhooks/stripe', [BillingController::class, 'webhook'])->middleware('throttle:120,1');

Route::middleware('clinic')->group(function () {
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/demo/{role}', [AuthController::class, 'demo'])->middleware('throttle:login');
    Route::get('/register', [OnboardingController::class, 'patientForm']);
    Route::post('/register', [OnboardingController::class, 'patient'])->middleware('throttle:login');
    Route::get('/forgot-password', [AuthController::class, 'forgotForm']);
    Route::post('/forgot-password', [AuthController::class, 'forgot'])->middleware('throttle:login');
    Route::get('/reset-password/{token}', [AuthController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password/{token}', [AuthController::class, 'reset'])->middleware('throttle:login');
    Route::get('/invitation/{token}', [OnboardingController::class, 'invitation']);
    Route::post('/invitation/{token}', [OnboardingController::class, 'accept'])->middleware('throttle:login');

    Route::middleware('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/security', [AuthController::class, 'security']);
        Route::post('/security', [AuthController::class, 'verifyMfa'])->middleware('throttle:mfa');
        Route::post('/email/verification-notification', [OnboardingController::class, 'verification'])->middleware('throttle:5,1');
        Route::get('/email/verify/{user}', [AuthController::class, 'verifyEmail'])->middleware('signed')->name('verification.verify');
        Route::middleware('mfa')->group(function () {
            Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
            Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
            Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
            Route::post('/profile/password', [ProfileController::class, 'password'])->middleware('throttle:mfa')->name('profile.password');
            Route::get('/dashboard', [DashboardController::class, 'index']);
            Route::get('/schedule', [DashboardController::class, 'schedule']);
            Route::get('/patients', [DashboardController::class, 'patients']);
            Route::post('/patients', [ClinicSettingsController::class, 'patient']);
            Route::get('/services', [DashboardController::class, 'services']);
            Route::get('/portal', [DashboardController::class, 'portal']);
            Route::get('/appointments/create', [AppointmentController::class, 'create']);
            Route::get('/appointments/slots', [AppointmentController::class, 'slots'])->middleware('throttle:booking');
            Route::post('/appointments', [AppointmentController::class, 'store'])->middleware('throttle:booking');
            Route::get('/appointments/{appointment}/edit', [AppointmentController::class, 'edit']);
            Route::post('/appointments/{appointment}/status', [AppointmentController::class, 'transition'])->middleware('throttle:booking');
            Route::post('/appointments/{appointment}/reschedule', [AppointmentController::class, 'reschedule'])->middleware('throttle:booking');
            Route::get('/patients/{patient}/clinical', [ClinicalController::class, 'show']);
            Route::get('/patients/{patient}/intake-export', [ClinicalController::class, 'export']);
            Route::post('/patients/{patient}/intake', [ClinicalController::class, 'intake']);
            Route::post('/patients/{patient}/notes', [ClinicalController::class, 'note']);
            Route::get('/patients/{patient}/care-assignments', [CareAssignmentController::class, 'index']);
            Route::post('/patients/{patient}/care-assignments', [CareAssignmentController::class, 'grant']);
            Route::post('/patients/{patient}/care-assignments/{assignment}/revoke', [CareAssignmentController::class, 'revoke']);
            Route::get('/settings', [ClinicSettingsController::class, 'index']);
            Route::post('/settings/profile', [ClinicSettingsController::class, 'profile']);
            Route::post('/settings/services', [ClinicSettingsController::class, 'service']);
            Route::post('/settings/resources', [ClinicSettingsController::class, 'resource']);
            Route::post('/settings/hours', [ClinicSettingsController::class, 'hours']);
            Route::post('/settings/blocks', [ClinicSettingsController::class, 'block']);
            Route::post('/settings/invitations', [ClinicSettingsController::class, 'invite']);
            Route::post('/settings/staff/{user}', [ClinicSettingsController::class, 'staff']);
            Route::post('/settings/resources/{type}/{resource}/deactivate', [ClinicSettingsController::class, 'deactivate']);
            Route::post('/settings/eligibility', [ClinicSettingsController::class, 'eligibility']);
            Route::get('/billing', [BillingController::class, 'index']);
            Route::post('/billing/checkout', [BillingController::class, 'checkout']);
            Route::post('/billing/portal', [BillingController::class, 'portal']);
            Route::get('/store', [CommerceController::class, 'index']);
            Route::post('/store/connection', [CommerceController::class, 'connect']);
            Route::post('/store/disconnect', [CommerceController::class, 'disconnect']);
            Route::get('/store/cart', [CommerceController::class, 'cart']);
            Route::post('/store/cart', [CommerceController::class, 'add']);
            Route::post('/store/cart/update', [CommerceController::class, 'update']);
            Route::post('/store/checkout', [CommerceController::class, 'checkout']);
            Route::get('/notifications', [NotificationController::class, 'index']);
            Route::post('/notifications/{message}/retry', [NotificationController::class, 'retry']);
        });
    });
});
