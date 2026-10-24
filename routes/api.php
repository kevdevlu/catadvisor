<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\StudentController;
use Illuminate\Support\Facades\Route;

// Every API route needs a signed-in session and is rate-limited.
// Which students a person may see is checked per request by the
// policies in app/Policies.
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/students', [StudentController::class, 'index']);
});
