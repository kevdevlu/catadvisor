<?php

namespace Tests\Feature;

use App\Ai\JsonModel;
use App\Models\Advisor;
use App\Models\ContactNote;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeJsonModel;
use Tests\TestCase;

/**
 * Editing, deleting, and undoing a delete.
 */
class EditDeleteNoteTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function note(string $externalId): ContactNote
    {
        return ContactNote::withTrashed()->where('external_id', $externalId)->firstOrFail();
    }

    private function signIn(string $username): void
    {
        $this->actingAs(Advisor::where('username', $username)->firstOrFail());
    }

    public function test_the_author_edits_a_note_in_place(): void
    {
        $this->signIn('reyes');
        $student = Student::where('sis_id', 'S1002')->firstOrFail();
        $student->brief()->create(['content' => ['summary' => 'Old'], 'notes_version' => 'old']);

        $this->patchJson("/api/notes/{$this->note('N006')->id}", [
            'contact_date' => '2026-03-15',
            'contact_type' => 'zoom',
            'note_text' => 'Struggling in Accounting II. SSN 123-45-6789 on the form. Referred to tutoring.',
            'is_private' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.date_label', 'Mar 15, 2026')
            ->assertJsonPath('data.type.label', 'Zoom')
            ->assertJsonPath('data.text', 'Struggling in Accounting II. SSN [SSN removed] on the form. Referred to tutoring.')
            ->assertJsonPath('data.edited_label', 'Edited Oct 5, 2026');

        $this->assertNull($student->fresh()->brief);
    }

    public function test_an_edit_needs_the_words_and_a_valid_date(): void
    {
        $this->signIn('reyes');

        $this->patchJson("/api/notes/{$this->note('N006')->id}", ['note_text' => ''])
            ->assertJsonValidationErrors(['note_text' => 'Write a note before saving.']);
        $this->patchJson("/api/notes/{$this->note('N006')->id}", ['contact_date' => '2026-10-06'])
            ->assertJsonValidationErrors(['contact_date' => 'The date cannot be later than today.']);
    }

    public function test_an_edited_note_can_be_checked_again(): void
    {
        $model = new FakeJsonModel([
            'brief' => ['summary' => 'S', 'open_items' => [], 'suggested_opener' => 'Hi.'],
            'contact_preference_suggestion' => null,
            'looks_sensitive' => false,
            'new_note_awaits_reply' => false,
            'new_note_shows_reply' => false,
            'follow_up' => ['action' => 'none', 'date' => null, 'reason' => null, 'evidence' => null],
        ]);
        $this->app->instance(JsonModel::class, $model);
        $this->signIn('reyes');
        $id = $this->note('N006')->id;

        $this->postJson("/api/notes/{$id}/check")->assertJsonPath('data.ran', true);
        $this->postJson("/api/notes/{$id}/check")->assertJsonPath('data.ran', false);

        $this->patchJson("/api/notes/{$id}", ['note_text' => 'Struggling in Accounting II. Tutoring set up.'])->assertOk();
        $this->postJson("/api/notes/{$id}/check")->assertJsonPath('data.ran', true);

        $this->assertCount(2, $model->calls);
    }

    public function test_a_deleted_note_is_kept_but_hidden_everywhere(): void
    {
        $this->signIn('reyes');
        $student = Student::where('sis_id', 'S1002')->firstOrFail();
        $n017 = $this->note('N017'); // the Jun 2 email, S1002's last contact

        $this->deleteJson("/api/notes/{$n017->id}")->assertNoContent();

        $kept = $this->note('N017');
        $this->assertTrue($kept->trashed());
        $this->assertSame(Advisor::where('username', 'reyes')->value('id'), $kept->deleted_by);

        $detail = $this->getJson("/api/students/{$student->id}");
        $this->assertNotContains($n017->id, collect($detail->json('data.notes'))->pluck('id'));

        // The last contact falls back to the note before it (Mar 14).
        $row = collect($this->getJson('/api/students')->json('data'))->firstWhere('sis_id', 'S1002');
        $this->assertSame('Mar 14, 2026', $row['last_contact']['label']);

        // "Waiting on a reply" came from that note: it stays, and names the
        // note as plain text instead of linking to it.
        $detail->assertJsonPath('data.waiting.source.note_id', null)
            ->assertJsonPath('data.waiting.source.note_name', 'Jun 2 email note');
    }

    public function test_undo_brings_a_deleted_note_back(): void
    {
        $this->signIn('reyes');
        $n017 = $this->note('N017');
        $this->travel(2)->hours();
        $this->deleteJson("/api/notes/{$n017->id}")->assertNoContent();
        $this->assertTrue($this->note('N017')->updated_at->eq($n017->updated_at));

        $this->postJson("/api/notes/{$n017->id}/restore")->assertOk()->assertJsonPath('data.id', $n017->id);

        $restored = $this->note('N017');
        $this->assertFalse($restored->trashed());
        $this->assertNull($restored->deleted_by);
        $this->assertTrue($restored->updated_at->eq($n017->updated_at));
        $this->getJson('/api/students/'.Student::where('sis_id', 'S1002')->value('id'))
            ->assertJsonPath('data.waiting.source.note_id', $n017->id);
    }

    public function test_an_edit_drops_an_answer_held_for_the_old_version(): void
    {
        $this->app->instance(JsonModel::class, new FakeJsonModel([
            'brief' => ['summary' => 'S', 'open_items' => [], 'suggested_opener' => 'Hi.'],
            'contact_preference_suggestion' => null,
            'looks_sensitive' => true,
            'new_note_awaits_reply' => true,
            'new_note_shows_reply' => false,
            'follow_up' => ['action' => 'none', 'date' => null, 'reason' => null, 'evidence' => null],
        ]));
        $this->signIn('reyes');
        $note = $this->note('N006');

        $this->postJson("/api/notes/{$note->id}/check")->assertJsonPath('data.ask_sensitive', true);
        $this->patchJson("/api/notes/{$note->id}", ['note_text' => 'Rewritten.'])->assertOk();

        $this->postJson("/api/notes/{$note->id}/keep")->assertJsonPath('data.ran', false);
    }

    public function test_only_the_author_can_edit_delete_or_restore(): void
    {
        $note = $this->note('N017'); // Reyes's

        foreach (['admin', 'okafor'] as $username) {
            $this->signIn($username);
            $this->patchJson("/api/notes/{$note->id}", ['note_text' => 'x'])->assertForbidden();
            $this->deleteJson("/api/notes/{$note->id}")->assertForbidden();
        }

        $this->signIn('reyes');
        $this->deleteJson("/api/notes/{$note->id}")->assertNoContent();

        $this->signIn('okafor');
        $this->postJson("/api/notes/{$note->id}/restore")->assertForbidden();
    }
}
