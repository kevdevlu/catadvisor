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
     * You must be able to see the student at all (their advisor, or the
     * admin). Then only a private note's author may read its words; everyone
     * else sees "Private note (hidden)" with the date and type.
     */
    public function readText(Advisor $user, ContactNote $note): bool
    {
        if (! $user->can('view', $note->student)) {
            return false;
        }

        return ! $note->is_private || $note->author_id === $user->id;
    }

    /**
     * Only the author edits a note, and only while the student is still
     * theirs: a student moved to another advisor takes the notes along. The
     * admin never writes notes, so this also keeps the admin read-only.
     */
    public function update(Advisor $user, ContactNote $note): bool
    {
        return $note->author_id === $user->id && $user->can('update', $note->student);
    }

    public function delete(Advisor $user, ContactNote $note): bool
    {
        return $this->update($user, $note);
    }

    public function restore(Advisor $user, ContactNote $note): bool
    {
        return $this->update($user, $note);
    }
}
