<?php

namespace Database\Seeders;

use App\Models\ContactNote;
use App\Models\Student;
use Illuminate\Database\Seeder;

class FixtureSeeder extends Seeder
{
    /**
     * Set the fields the app normally fills in as advisors work,
     * so the demo shows them without needing an API key. Each one
     * points back to the dataset note it came from.
     */
    public function run(): void
    {
        // Contact preference from N001: phone only, because email goes to a
        // shared family account.
        $n001 = $this->note('N001');
        $this->student('S1002')->update([
            'contact_preference' => 'Phone only, best after 5 pm. Email goes to a shared family account.',
            'contact_preference_note_id' => $n001->id,
            'contact_preference_set_by' => $n001->author_id,
            'contact_preference_set_at' => $n001->contact_date,
        ]);

        // Waiting on a reply: the last outreach to each of these students got
        // no answer, for example N018 "Left voicemail. No response."
        $waiting = ['S1002' => 'N017', 'S1030' => 'N023', 'S1014' => 'N015', 'S1044' => 'N018'];

        foreach ($waiting as $sisId => $externalId) {
            $source = $this->note($externalId);
            $this->student($sisId)->update([
                'awaiting_reply_since' => $source->contact_date,
                'awaiting_reply_note_id' => $source->id,
            ]);
        }

        // Next follow-ups. N051 names a withdrawal deadline but no
        // date, so this follow-up has no date. A null set_by means "set by
        // AI", which is what the on-save check would have done.
        $n051 = $this->note('N051');
        $this->student('S1047')->update([
            'follow_up_on' => null,
            'follow_up_reason' => 'Withdrawal deadline approaching (no date in the note)',
            'follow_up_note_id' => $n051->id,
            'follow_up_set_by' => null,
            'follow_up_set_at' => $n051->contact_date,
        ]);

        // N041 is private, so the AI never reads it. Its author set this one.
        $n041 = $this->note('N041');
        $this->student('S1037')->update([
            'follow_up_on' => '2026-10-15',
            'follow_up_reason' => 'FAFSA verification',
            'follow_up_note_id' => $n041->id,
            'follow_up_set_by' => $n041->author_id,
            'follow_up_set_at' => $n041->contact_date,
        ]);
    }

    private function note(string $externalId): ContactNote
    {
        return ContactNote::where('external_id', $externalId)->firstOrFail();
    }

    private function student(string $sisId): Student
    {
        return Student::where('sis_id', $sisId)->firstOrFail();
    }
}
