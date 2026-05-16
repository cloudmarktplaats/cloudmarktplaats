<?php

use App\Http\Controllers\Auth\OAuthController;
use App\Http\Controllers\Auth\Web3Controller;
use App\Http\Controllers\HealthController;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Auth\SiweOnboarding;
use App\Livewire\Auth\VerifyEmailNotice;
use App\Livewire\Profile\Security as ProfileSecurity;
use App\Livewire\Profile\TwoFactorSetup;
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

Route::get('/login', Login::class)->middleware('guest')->name('login');

Route::post('/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect('/');
})->middleware('auth')->name('logout');

Route::get('/forgot-password', ForgotPassword::class)->middleware('guest')->name('password.request');

Route::get('/reset-password/{token}', ResetPassword::class)->middleware('guest')->name('password.reset');

// OAuth — provider-agnostic. Allowed providers are whitelisted in
// App\Services\Auth\OAuthProviderRegistry; unknown providers 404.
Route::get('/oauth/{provider}/redirect', [OAuthController::class, 'redirect']);
Route::get('/oauth/{provider}/callback', [OAuthController::class, 'callback']);

// SIWE (Sign-In With Ethereum) — JSON endpoints driven by the wallet
// adapter on the front-end. /verify is CSRF-exempt (see bootstrap/app.php)
// because the wallet adapter has no session cookie; replay is blocked by
// the single-use nonce stored in auth_nonces.
Route::get('/auth/web3/nonce', [Web3Controller::class, 'nonce']);
Route::post('/auth/web3/verify', [Web3Controller::class, 'verify']);
Route::get('/auth/web3/onboarding/{address}', SiweOnboarding::class)
    ->where('address', '0x[a-fA-F0-9]{40}')
    ->middleware('guest')
    ->name('siwe.onboarding');

// Profile / security
Route::get('/profile/security', ProfileSecurity::class)
    ->middleware('auth')
    ->name('profile.security');

Route::get('/profile/security/2fa', TwoFactorSetup::class)
    ->middleware('auth')
    ->name('profile.security.2fa');
