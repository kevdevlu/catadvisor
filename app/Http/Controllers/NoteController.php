<?php

namespace App\Http\Controllers;

use App\Enums\ContactType;
use App\Http\Resources\NoteResource;
use App\Models\ContactNote;
use App\Models\Student;
use App\Support\Clock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class NoteController extends Controller
{
    /**
     * Add a note to a student. The route checks that the
     * signed-in advisor is this student's advisor. The author always comes
     * from the login, never from the form, and any SSN in the text
     * is removed by the model before it is stored.
     *
     * Returns the saved note right away. The AI check runs as its own
     * request afterward, so saving never waits on it.
     */
    public function store(Request $request, Student $student): JsonResponse
    {
        $fields = $request->validate([
            // A note can't be dated after today. Rationale: it records
            // something that already happened.
            'contact_date' => ['required', Rule::date()->beforeOrEqual(Clock::today())],
            'contact_type' => ['required', Rule::enum(ContactType::class)],
            'note_text' => ['required', 'string', 'max:5000'],
            'is_private' => ['sometimes', 'boolean'],
        ], [
            'contact_date.required' => 'Pick a date.',
            'contact_date.before_or_equal' => 'The date cannot be later than today.',
            'contact_type.required' => 'Choose a contact type.',
            'note_text.required' => 'Write a note before saving.',
            'note_text.max' => 'The note is too long. Keep it under 5,000 characters.',
        ]);

        $note = new ContactNote($fields);
        $note->student()->associate($student);
        $note->author()->associate($request->user());

        $waitingCleared = DB::transaction(function () use ($note, $student) {
            $note->save();

            // Any change to a student's notes makes the cached prep brief
            // out of date.
            $student->brief()->delete();

            return $student->clearWaitingIfAnswered($note);
        });

        return (new NoteResource($note->load('author')))
            ->additional(['meta' => ['waiting_cleared' => $waitingCleared]])
            ->response()
            ->setStatusCode(201);
    }
}
