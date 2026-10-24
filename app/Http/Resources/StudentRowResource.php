<?php

namespace App\Http\Resources;

use App\Models\Advisor;
use App\Models\ContactNote;
use App\Support\CaseloadEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the first screen's table. Dates and day counts are
 * worked out here, so the React app does no date math.
 *
 * @property CaseloadEntry $resource
 */
class StudentRowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $student = $this->resource->student;
        $status = $this->resource->status;

        return [
            'id' => $student->id,
            'sis_id' => $student->sis_id,
            'first_name' => $student->first_name,
            'last_name' => $student->last_name,
            'email' => $student->email,
            'major' => $student->major,
            'class_year' => $student->class_year,
            'gpa' => $student->gpa,
            'risk' => [
                'value' => $student->risk_flag->value,
                'label' => $student->risk_flag->label(),
                'rank' => $student->risk_flag->rank(),
                'target_days' => $student->risk_flag->targetDays(),
            ],
            'advisor' => $student->advisor->display_name,
            'last_contact' => $status->lastContact ? [
                'date' => $status->lastContact->toDateString(),
                'label' => $status->lastContact->format('M j, Y'),
                'days_ago' => $status->daysSinceContact,
            ] : null,
            'days_until_due' => $status->daysUntilDue,
            // When the next contact is due: the last contact plus the risk
            // level's target. Shown in the Status chip's popover.
            'contact_by_label' => $status->lastContact?->addDays($student->risk_flag->targetDays())->format('M j, Y'),
            'never_contacted' => $status->neverContacted(),
            'is_due' => $status->isDue(),
            // The three badges open popovers with these details.
            'contact_preference' => StudentLines::preference($student),
            'waiting' => StudentLines::waiting($student, $request->user()),
            'follow_up' => StudentLines::followUp($student, $request->user()),
            'follow_up_due' => $status->followUpDue,
            'last_note' => $this->lastNote($request->user(), $status->lastNote),
        ];
    }

    /**
     * The gray "Last note" line under a row. A private note's words go only
     * to its author; everyone else gets the date and type.
     */
    private function lastNote(Advisor $viewer, ?ContactNote $note): ?array
    {
        if ($note === null) {
            return null;
        }

        return [
            'date_label' => $note->contact_date->format('M j'),
            'type' => $note->contact_type->label(),
            'text' => $viewer->can('readText', $note) ? $note->note_text : null,
            'is_private' => $note->is_private,
        ];
    }
}
