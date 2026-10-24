<?php

namespace App\Policies;

use App\Models\Advisor;
use App\Models\Student;

/**
 * Who may see or change a student's record.
 */
class StudentPolicy
{
    /**
     * An advisor sees only their own students. The admin sees everyone.
     */
    public function view(Advisor $user, Student $student): bool
    {
        return $user->isAdmin() || $student->advisor_id === $user->id;
    }

    /**
     * Adding notes, or editing the contact preference, follow-up, or
     * waiting-on-a-reply fields, is for the student's own advisor only. The
     * admin is read-only.
     */
    public function update(Advisor $user, Student $student): bool
    {
        return ! $user->isAdmin() && $student->advisor_id === $user->id;
    }
}
