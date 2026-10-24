<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// Signing in and out. These use the normal web session and CSRF
// check. The login rate limit is defined in AppServiceProvider.
Route::get('/login/accounts', [AuthController::class, 'accounts'])->middleware('throttle:30,1');
Route::post('/login', [AuthController::class, 'login'])->middleware(['guest', 'throttle:login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');

// The React app. /login is open to signed-out visitors. Every other address
// outside /api needs a session, so a signed-out visitor is sent to /login.
// React Router picks the page, for example /students/9b1f0c2e-...
Route::view('/login', 'app')->middleware('guest')->name('login');
Route::view('/{path?}', 'app')->where('path', '(?!api(?:/|$)).*')->middleware('auth');
