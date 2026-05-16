<?php

use App\Http\Controllers\HealthController;
use App\Livewire\Auth\Register;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/healthz', HealthController::class);

Route::get('/register', Register::class)->middleware('guest')->name('register');
