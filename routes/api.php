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
    Route::get('/students/{student}/brief', [StudentController::class, 'brief'])->can('view', 'student')->middleware('throttle:ai-brief');

    // Writes: only the student's own advisor. The admin is read-only.
    Route::middleware('can:update,student')->group(function () {
        Route::post('/students/{student}/notes', [NoteController::class, 'store']);
        Route::put('/students/{student}/contact-preference', [StudentFieldsController::class, 'updatePreference']);
        Route::put('/students/{student}/follow-up', [StudentFieldsController::class, 'updateFollowUp']);
        Route::delete('/students/{student}/follow-up', [StudentFieldsController::class, 'clearFollowUp']);
        Route::delete('/students/{student}/waiting', [StudentFieldsController::class, 'clearWaiting']);
    });

    // A note's author only (ContactNotePolicy). The AI routes have a
    // tighter rate limit of their own, since each call costs money.
    Route::patch('/notes/{note}', [NoteController::class, 'update'])->can('update', 'note');
    Route::delete('/notes/{note}', [NoteController::class, 'destroy'])->can('delete', 'note');
    Route::post('/notes/{note}/restore', [NoteController::class, 'restore'])->can('restore', 'note')->withTrashed();
    Route::post('/notes/{note}/check', [NoteController::class, 'check'])->can('update', 'note')->middleware('throttle:ai-check');
    Route::post('/notes/{note}/keep', [NoteController::class, 'keep'])->can('update', 'note')->middleware('throttle:ai-check');
});
