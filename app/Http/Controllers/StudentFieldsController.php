<?php

namespace App\Http\Controllers;

use App\Http\Resources\StudentDetailResource;
use App\Models\Student;
use App\Support\Clock;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The advisor's own edits to the student-level lines on the student page:
 * the contact preference, the next follow-up, and "waiting on a reply".
 * The routes check that the signed-in advisor is this
 * student's advisor. Each edit records who made it and drops the link to a
 * source note, since the advisor, not a note, is now the source. The one
 * exception is a follow-up whose reason didn't change (see below).
 *
 * Each returns the whole student page, so the view can show the change.
 */
class StudentFieldsController extends Controller
{
    public function updatePreference(Request $request, Student $student): StudentDetailResource
    {
        $fields = $request->validate([
            // "present": the field must be sent, even if empty. Rationale: a
            // request that forgot it shouldn't remove the preference.
            'contact_preference' => ['present', 'nullable', 'string', 'max:500'],
            // When the advisor accepts the AI's suggestion, the note it came
            // from. Only one of this student's notes that everyone
            // may read, since everyone sees the preference.
            'note_id' => ['nullable', Rule::exists('contact_notes', 'id')
                ->where('student_id', $student->id)
                ->where('is_private', false)
                ->whereNull('deleted_at')],
        ]);

        // Saving an empty box removes the preference. The model removes any
        // SSN from the text before it is stored.
        $text = $fields['contact_preference'] ?? null;

        $student->forceFill([
            'contact_preference' => $text,
            'contact_preference_note_id' => $text === null ? null : ($fields['note_id'] ?? null),
            'contact_preference_set_by' => $text === null ? null : $request->user()->id,
            'contact_preference_set_at' => $text === null ? null : Clock::now(),
        ])->save();

        return StudentDetailResource::for($student);
    }

    public function updateFollowUp(Request $request, Student $student): StudentDetailResource
    {
        // The date is optional, as when a deadline is known but not its day.
        $fields = $request->validate([
            'date' => ['nullable', Rule::date()->afterOrEqual('2000-01-01')->beforeOrEqual(Clock::today()->addYears(2))],
            'reason' => ['nullable', 'string', 'max:200', 'required_without:date'],
        ], [
            'date.date' => 'Pick a valid date.',
            'date.after_or_equal' => 'Pick a date in 2000 or later.',
            'date.before_or_equal' => 'Pick a date within the next two years.',
            'reason.required_without' => 'Add a date or a reason.',
        ]);

        $reason = $fields['reason'] ?? 'Follow up';

        // If the reason is unchanged (only the date was edited), it still
        // comes from the same note, so keep the link. Rationale: a reason
        // taken from a private note then stays hidden from everyone but the
        // note's author. New reason text is the advisor's own,
        // and everyone who can see the student sees it.
        $sameReason = $student->follow_up_note_id !== null && $reason === $student->follow_up_reason;

        $student->forceFill([
            'follow_up_on' => $fields['date'] ?? null,
            'follow_up_reason' => $reason,
            'follow_up_note_id' => $sameReason ? $student->follow_up_note_id : null,
            'follow_up_set_by' => $request->user()->id,
            'follow_up_set_at' => Clock::now(),
        ])->save();

        return StudentDetailResource::for($student);
    }

    public function clearFollowUp(Student $student): StudentDetailResource
    {
        $student->forceFill([
            'follow_up_on' => null,
            'follow_up_reason' => null,
            'follow_up_note_id' => null,
            'follow_up_set_by' => null,
            'follow_up_set_at' => null,
        ])->save();

        return StudentDetailResource::for($student);
    }

    public function clearWaiting(Student $student): StudentDetailResource
    {
        $student->forceFill(['awaiting_reply_since' => null, 'awaiting_reply_note_id' => null])->save();

        return StudentDetailResource::for($student);
    }
}
