<?php

use App\Http\Controllers\HealthController;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\VerifyEmailNotice;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/healthz', HealthController::class);

Route::get('/register', Register::class)->middleware('guest')->name('register');

Route::get('/email/verify-notice', VerifyEmailNotice::class)
    ->middleware('auth')
    ->name('verification.notice');

Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill();

    return redirect('/');
})->middleware(['auth', 'signed', 'throttle:6,1'])->name('verification.verify');

Route::post('/email/verification-notification', function () {
    auth()->user()?->sendEmailVerificationNotification();

    return back()->with('status', 'verification-link-sent');
})->middleware(['auth', 'throttle:6,1'])->name('verification.send');
