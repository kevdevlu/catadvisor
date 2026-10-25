<?php

namespace Tests\Feature;

use App\Models\Advisor;
use App\Models\ContactNote;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Reset demo data": the admin puts every student and note back to the
 * original dataset.
 */
class DemoResetTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function advisor(string $username): Advisor
    {
        return Advisor::where('username', $username)->firstOrFail();
    }

    /**
     * Everything the reset should put back, keyed by the dataset's own IDs,
     * since the reloaded records get new UUIDs.
     */
    private function snapshot(): array
    {
        return [
            'students' => Student::orderBy('sis_id')->get()
                ->mapWithKeys(fn (Student $student) => [$student->sis_id => $student->only([
                    'first_name', 'last_name', 'risk_flag', 'contact_preference', 'follow_up_on', 'follow_up_reason', 'awaiting_reply_since',
                ])])->all(),
            'notes' => ContactNote::withTrashed()->orderBy('external_id')->get()
                ->map(fn (ContactNote $note) => [$note->external_id, $note->note_text, $note->is_private, $note->deleted_at])->all(),
        ];
    }

    public function test_the_admin_puts_the_original_dataset_back(): void
    {
        $original = $this->snapshot();
        $student = Student::where('sis_id', 'S1002')->firstOrFail();

        // An advisor tries things out: a new note, a changed preference, and
        // a deleted note.
        $this->actingAs($this->advisor('reyes'));
        $this->postJson("/api/students/{$student->id}/notes", [
            'contact_date' => '2026-10-05', 'contact_type' => 'phone', 'note_text' => 'Called to check in.',
        ])->assertCreated();
        $this->putJson("/api/students/{$student->id}/contact-preference", ['contact_preference' => 'Email is fine.'])->assertOk();
        $this->deleteJson('/api/notes/'.ContactNote::where('external_id', 'N017')->value('id'))->assertNoContent();
        $this->assertNotEquals($original, $this->snapshot());

        $this->actingAs($this->advisor('admin'))->postJson('/api/demo/reset')->assertNoContent();

        $this->assertEquals($original, $this->snapshot());
        // The accounts stay, so the admin is still signed in.
        $this->getJson('/api/me')->assertOk();
    }

    public function test_only_the_admin_can_reset(): void
    {
        $this->postJson('/api/demo/reset')->assertUnauthorized();
        $this->actingAs($this->advisor('reyes'))->postJson('/api/demo/reset')->assertForbidden();
    }
}
