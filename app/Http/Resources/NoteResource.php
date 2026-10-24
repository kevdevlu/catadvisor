<?php

namespace App\Http\Resources;

use App\Models\ContactNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One note in a student's history. A private note's words go only
 * to its author. Everyone else gets the date, type, and author, and the view
 * shows "Private note (hidden)".
 *
 * Expects the note's author to be loaded.
 *
 * @mixin ContactNote
 */
class NoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user();

        return [
            'id' => $this->id,
            'contact_date' => $this->contact_date->toDateString(),
            'date_label' => $this->contact_date->format('M j, Y'),
            'type' => [
                'value' => $this->contact_type->value,
                'label' => $this->contact_type->label(),
            ],
            'author' => $this->author->display_name,
            'is_mine' => $this->author_id === $viewer->id,
            'is_private' => $this->is_private,
            'ai_read' => $this->ai_read,
            'text' => $viewer->can('readText', $this->resource) ? $this->note_text : null,
            'edited_label' => $this->updated_by ? 'Edited '.$this->updated_at->format('M j, Y') : null,
        ];
    }
}
