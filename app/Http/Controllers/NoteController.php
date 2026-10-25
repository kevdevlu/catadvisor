<?php

namespace App\Http\Controllers;

use App\Ai\AdvisingAssistant;
use App\Enums\ContactType;
use App\Http\Resources\NoteResource;
use App\Models\ContactNote;
use App\Models\Student;
use App\Support\Clock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class NoteController extends Controller
{
    private const MESSAGES = [
        'contact_date.required' => 'Pick a date.',
        'contact_date.date' => 'Pick a valid date.',
        'contact_date.after_or_equal' => 'Pick a date in 2000 or later.',
        'contact_date.before_or_equal' => 'The date cannot be later than today.',
        'contact_type.required' => 'Choose a contact type.',
        'note_text.required' => 'Write a note before saving.',
        'note_text.max' => 'The note is too long. Keep it under 5,000 characters.',
    ];

    /**
     * Add a note to a student. The route checks that the signed-in advisor
     * is this student's advisor. The author always comes from the login,
     * never from the form, and any SSN in the text is removed by the model
     * before it is stored.
     *
     * Returns the saved note right away. The AI check runs as its own
     * request afterward, so saving never waits on it.
     */
    public function store(Request $request, Student $student): JsonResponse
    {
        $fields = $request->validate(self::rules(partial: false), self::MESSAGES);

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

        // refresh() reads back the database defaults, such as is_private
        // false when the form left it out.
        return (new NoteResource($note->refresh()->load('author')))
            ->additional(['meta' => ['waiting_cleared' => $waitingCleared]])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Change a note. The route checks that the signed-in advisor
     * wrote it. Any field may be sent alone; the "Mark private" answer to
     * the AI's sensitive-note question sends only is_private.
     *
     * Making a note private drops what the AI took from it.
     * The page shows "Edited" only when the date, type, or words change.
     */
    public function update(Request $request, ContactNote $note): NoteResource
    {
        $fields = $request->validate(self::rules(partial: true), self::MESSAGES);

        $becomesPrivate = ! $note->is_private && ($fields['is_private'] ?? false);
        $note->fill($fields);

        if ($note->isDirty(['contact_date', 'contact_type', 'note_text'])) {
            $note->updated_by = $request->user()->id;
        }

        // Any change makes a new version of the note, which the AI may check
        // again. An answer held for the old version is dropped, so "Keep as
        // is" can't apply it.
        $changed = $note->isDirty();

        if ($changed) {
            $note->revision++;
        }

        DB::transaction(function () use ($note, $becomesPrivate, $changed) {
            $note->save();
            $note->student->brief()->delete();

            if ($becomesPrivate) {
                $note->student->unlinkPreferenceFrom($note);
            }

            if ($changed) {
                AdvisingAssistant::forgetPending($note);
            }
        });

        return new NoteResource($note->refresh()->load('author'));
    }

    /**
     * Delete a note. It is kept in the database, hidden everywhere in the
     * app, with who deleted it and when: advising notes are education
     * records, and the school may have retention rules. The page offers
     * "Undo" for a few seconds (restore()).
     *
     * What other lines took from the note stays, and the page names the
     * note as plain text instead of a link.
     */
    public function destroy(Request $request, ContactNote $note): Response
    {
        // Without timestamps, so a restored note keeps its "Edited <date>".
        DB::transaction(fn () => ContactNote::withoutTimestamps(function () use ($request, $note) {
            $note->forceFill(['deleted_by' => $request->user()->id])->save();
            $note->delete();
            $note->student->brief()->delete();
            AdvisingAssistant::forgetPending($note);
        }));

        return response()->noContent();
    }

    /**
     * Undo a delete.
     */
    public function restore(ContactNote $note): NoteResource
    {
        DB::transaction(fn () => ContactNote::withoutTimestamps(function () use ($note) {
            $note->restore();
            $note->forceFill(['deleted_by' => null])->save();
            $note->student->brief()->delete();
        }));

        return new NoteResource($note->refresh()->load('author'));
    }

    /**
     * The AI check, which the page calls right after a save or an edit, so
     * saving never waits on the AI. It saves the brief, the
     * follow-up, and "waiting on a reply", and answers with what to ask the
     * advisor. With no answer from the AI, nothing changes and nothing is
     * asked.
     */
    public function check(ContactNote $note, AdvisingAssistant $assistant): JsonResponse
    {
        return response()->json(['data' => $assistant->checkNote($note)->toArray()]);
    }

    /**
     * The advisor answered "Keep as is" to "This note may be sensitive":
     * apply what the check found and held back.
     */
    public function keep(ContactNote $note, AdvisingAssistant $assistant): JsonResponse
    {
        return response()->json(['data' => $assistant->keep($note)->toArray()]);
    }

    /**
     * The note fields. A new note needs all of them; an edit ($partial) may
     * send any of them alone, but a field it sends can't be empty.
     */
    private static function rules(bool $partial): array
    {
        $presence = $partial ? ['sometimes'] : [];

        return [
            // A note can't be dated after today. Rationale: it records
            // something that already happened. The 2000 floor catches typos
            // like 1026, which would make a student look thousands of days
            // overdue.
            'contact_date' => [...$presence, 'required', Rule::date()->afterOrEqual('2000-01-01')->beforeOrEqual(Clock::today())],
            'contact_type' => [...$presence, 'required', Rule::enum(ContactType::class)],
            'note_text' => [...$presence, 'required', 'string', 'max:5000'],
            'is_private' => ['sometimes', 'boolean'],
        ];
    }
}
