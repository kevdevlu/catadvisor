<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentFieldsController;
use Illuminate\Support\Facades\Route;

// Every API route needs a signed-in session and is rate-limited.
// Which students a person may see or change is checked per
// request by the policies in app/Policies: "can:view,student"
// runs StudentPolicy::view() on the student in the address.
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/students', [StudentController::class, 'index']);
    Route::get('/students/{student}', [StudentController::class, 'show'])->can('view', 'student');

    // Writes: only the student's own advisor. The admin is read-only.
    Route::middleware('can:update,student')->group(function () {
        Route::post('/students/{student}/notes', [NoteController::class, 'store']);
        Route::put('/students/{student}/contact-preference', [StudentFieldsController::class, 'updatePreference']);
        Route::put('/students/{student}/follow-up', [StudentFieldsController::class, 'updateFollowUp']);
        Route::delete('/students/{student}/follow-up', [StudentFieldsController::class, 'clearFollowUp']);
        Route::delete('/students/{student}/waiting', [StudentFieldsController::class, 'clearWaiting']);
    });
});
