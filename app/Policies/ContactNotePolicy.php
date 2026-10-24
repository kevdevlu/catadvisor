<?php

namespace App\Policies;

use App\Models\Advisor;
use App\Models\ContactNote;

/**
 * Who may read, edit, or delete a note.
 */
class ContactNotePolicy
{
    /**
     * Only a private note's author may read its words. Everyone else sees
     * "Private note (hidden)" with the date and type.
     */
    public function readText(Advisor $user, ContactNote $note): bool
    {
        return ! $note->is_private || $note->author_id === $user->id;
    }

    /**
     * Only the author edits a note. The admin never writes notes, so this
     * also keeps the admin read-only.
     */
    public function update(Advisor $user, ContactNote $note): bool
    {
        return $note->author_id === $user->id;
    }

    public function delete(Advisor $user, ContactNote $note): bool
    {
        return $note->author_id === $user->id;
    }

    public function restore(Advisor $user, ContactNote $note): bool
    {
        return $note->author_id === $user->id;
    }
}
