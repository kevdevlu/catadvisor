<?php

namespace Tests\Feature;

use App\Models\Advisor;
use App\Models\ContactNote;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Adding a note on the student page.
 */
class AddNoteTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function student(string $sisId): Student
    {
        return Student::where('sis_id', $sisId)->firstOrFail();
    }

    private function addAs(string $username, string $sisId, array $fields = []): TestResponse
    {
        $this->actingAs(Advisor::where('username', $username)->firstOrFail());

        return $this->postJson("/api/students/{$this->student($sisId)->id}/notes", [
            'contact_date' => '2026-10-05',
            'contact_type' => 'phone',
            'note_text' => 'Called to check in about midterms.',
            ...$fields,
        ]);
    }

    public function test_the_author_comes_from_the_login_and_ssns_are_removed(): void
    {
        $okafor = Advisor::where('username', 'okafor')->firstOrFail();

        $this->addAs('reyes', 'S1008', [
            'note_text' => 'Needed a form signed. SSN 123-45-6789 given.',
            'author_id' => $okafor->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.author', 'Reyes')
            ->assertJsonPath('data.text', 'Needed a form signed. SSN [SSN removed] given.')
            ->assertJsonPath('data.date_label', 'Oct 5, 2026');

        $this->assertDatabaseMissing('contact_notes', ['author_id' => $okafor->id, 'student_id' => $this->student('S1008')->id, 'contact_date' => '2026-10-05']);
    }

    public function test_saving_a_note_clears_the_cached_brief(): void
    {
        $this->student('S1008')->brief()->create(['content' => ['summary' => 'Old'], 'notes_version' => 'old']);

        $this->addAs('reyes', 'S1008')->assertCreated();

        $this->assertNull($this->student('S1008')->brief);
    }

    public function test_the_form_gets_plain_messages_for_mistakes(): void
    {
        $this->addAs('reyes', 'S1008', ['contact_date' => '2026-10-06'])
            ->assertJsonValidationErrors(['contact_date' => 'The date cannot be later than today.']);

        $this->addAs('reyes', 'S1008', ['contact_type' => ''])
            ->assertJsonValidationErrors(['contact_type' => 'Choose a contact type.']);

        $this->addAs('reyes', 'S1008', ['note_text' => '   '])
            ->assertJsonValidationErrors(['note_text' => 'Write a note before saving.']);

        $this->addAs('reyes', 'S1008', ['contact_type' => 'text message'])
            ->assertJsonValidationErrors(['contact_type']);
    }

    public function test_only_the_students_own_advisor_can_add_notes(): void
    {
        $this->addAs('admin', 'S1008')->assertForbidden();
        $this->addAs('okafor', 'S1008')->assertForbidden();
    }

    public function test_a_newer_two_way_contact_clears_waiting_on_a_reply(): void
    {
        // S1002 has been waiting on a reply since the Jun 2 email.
        $this->addAs('reyes', 'S1002', ['contact_type' => 'in_person'])
            ->assertJsonPath('meta.waiting_cleared', true);

        $this->assertNull($this->student('S1002')->awaiting_reply_since);
    }

    public function test_email_note_only_and_older_notes_leave_waiting_on_a_reply_alone(): void
    {
        $this->addAs('reyes', 'S1002', ['contact_type' => 'email'])
            ->assertJsonPath('meta.waiting_cleared', false);

        $this->addAs('reyes', 'S1002', ['contact_type' => 'note'])
            ->assertJsonPath('meta.waiting_cleared', false);

        $this->addAs('reyes', 'S1002', ['contact_type' => 'zoom', 'contact_date' => '2026-05-01'])
            ->assertJsonPath('meta.waiting_cleared', false);

        $this->assertNotNull($this->student('S1002')->awaiting_reply_since);
    }

    public function test_a_phone_note_waits_for_the_ai_unless_it_is_private(): void
    {
        // A phone note may be a missed call, like N018 "Left voicemail. No
        // response." The AI check decides for one it can read.
        $this->addAs('okafor', 'S1044', ['contact_type' => 'phone', 'note_text' => 'Left voicemail again. No response.'])
            ->assertJsonPath('meta.waiting_cleared', false);
        $this->assertNotNull($this->student('S1044')->awaiting_reply_since);

        // The AI never reads a private note, so it counts as an answer now.
        $this->addAs('okafor', 'S1044', ['contact_type' => 'phone', 'is_private' => true])
            ->assertJsonPath('meta.waiting_cleared', true);
        $this->assertNull($this->student('S1044')->awaiting_reply_since);
    }

    public function test_a_note_saved_without_the_private_box_comes_back_as_not_private(): void
    {
        $this->addAs('reyes', 'S1008')
            ->assertJsonPath('data.is_private', false)
            ->assertJsonPath('data.ai_read', false);
    }

    public function test_a_date_before_2000_is_refused(): void
    {
        $this->addAs('reyes', 'S1008', ['contact_date' => '1999-12-31'])
            ->assertJsonValidationErrors(['contact_date' => 'Pick a date in 2000 or later.']);

        $this->addAs('reyes', 'S1008', ['contact_date' => 'Oct 5'])
            ->assertJsonValidationErrors(['contact_date' => 'Pick a valid date.']);
    }

    public function test_of_two_notes_on_the_same_day_the_later_one_is_listed_first(): void
    {
        // Saved in the same second, so only the time-ordered id tells them
        // apart.
        $this->addAs('reyes', 'S1008', ['note_text' => 'First call.']);
        $this->addAs('reyes', 'S1008', ['note_text' => 'Second call.']);

        $this->getJson("/api/students/{$this->student('S1008')->id}")->assertJsonPath('data.notes.0.text', 'Second call.');
        $this->getJson('/api/students?q=S1008')->assertJsonPath('data.0.last_note.text', 'Second call.');
    }

    public function test_a_note_only_entry_is_not_a_contact(): void
    {
        // S1018 has never been contacted.
        $this->addAs('lindqvist', 'S1018', ['contact_type' => 'note', 'note_text' => 'Asked the registrar about transfer credits.'])
            ->assertCreated()
            ->assertJsonPath('data.type.label', 'Note only');

        $this->getJson("/api/students/{$this->student('S1018')->id}")
            ->assertJsonPath('data.due.reasons.0.emphasis', 'Never contacted.');
    }

    public function test_a_private_notes_words_reach_only_its_author(): void
    {
        $this->addAs('reyes', 'S1008', ['note_text' => 'Shared a family matter.', 'is_private' => true])
            ->assertJsonPath('data.is_private', true)
            ->assertJsonPath('data.text', 'Shared a family matter.');

        $this->actingAs(Advisor::where('username', 'admin')->firstOrFail());
        $note = ContactNote::where('note_text', 'Shared a family matter.')->firstOrFail();

        $notes = collect($this->getJson("/api/students/{$this->student('S1008')->id}")->json('data.notes'));
        $this->assertNull($notes->firstWhere('id', $note->id)['text']);
    }
}
