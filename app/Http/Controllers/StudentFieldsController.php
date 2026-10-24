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
 * source note, since the advisor, not a note, is now the source.
 *
 * Each returns the whole student page, so the view can show the change.
 */
class StudentFieldsController extends Controller
{
    public function updatePreference(Request $request, Student $student): StudentDetailResource
    {
        $fields = $request->validate(['contact_preference' => ['nullable', 'string', 'max:500']]);

        // Saving an empty box removes the preference. The model removes any
        // SSN from the text before it is stored.
        $text = $fields['contact_preference'] ?? null;

        $student->forceFill([
            'contact_preference' => $text,
            'contact_preference_note_id' => null,
            'contact_preference_set_by' => $text === null ? null : $request->user()->id,
            'contact_preference_set_at' => $text === null ? null : Clock::now(),
        ])->save();

        return StudentDetailResource::for($student);
    }

    public function updateFollowUp(Request $request, Student $student): StudentDetailResource
    {
        // The date is optional, as when a deadline is known but not its day.
        $fields = $request->validate([
            'date' => ['nullable', Rule::date()],
            'reason' => ['nullable', 'string', 'max:200', 'required_without:date'],
        ], [
            'reason.required_without' => 'Add a date or a reason.',
        ]);

        $student->forceFill([
            'follow_up_on' => $fields['date'] ?? null,
            'follow_up_reason' => $fields['reason'] ?? 'Follow up',
            'follow_up_note_id' => null,
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
