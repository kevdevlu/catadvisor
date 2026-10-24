<?php

namespace Tests\Feature;

use App\Ai\JsonModel;
use App\Models\Advisor;
use App\Models\ContactNote;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Fakes\FakeJsonModel;
use Tests\TestCase;

/**
 * The AI prep brief and marking a checked note private,
 * against a fake model.
 */
class PrepBriefTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private FakeJsonModel $model;

    protected function setUp(): void
    {
        parent::setUp();

        $this->model = new FakeJsonModel(['summary' => 'Where things stand.', 'open_items' => ['Call about tutoring.'], 'suggested_opener' => 'Hi!']);
        $this->app->instance(JsonModel::class, $this->model);
    }

    private function student(string $sisId): Student
    {
        return Student::where('sis_id', $sisId)->firstOrFail();
    }

    private function briefAs(string $username, string $sisId): TestResponse
    {
        $this->actingAs(Advisor::where('username', $username)->firstOrFail());

        return $this->getJson("/api/students/{$this->student($sisId)->id}/brief");
    }

    public function test_the_brief_is_written_once_and_then_cached(): void
    {
        $this->briefAs('reyes', 'S1002')
            ->assertOk()
            ->assertJsonPath('data.summary', 'Where things stand.')
            ->assertJsonPath('data.notes_used', 3)
            ->assertJsonPath('data.private_left_out', 0)
            ->assertJsonPath('meta.reason', 'ok');

        $this->briefAs('reyes', 'S1002')->assertJsonPath('data.summary', 'Where things stand.');

        $this->assertCount(1, $this->model->calls);
    }

    public function test_a_new_note_means_a_new_brief(): void
    {
        $this->briefAs('reyes', 'S1002');
        $this->postJson("/api/students/{$this->student('S1002')->id}/notes", [
            'contact_date' => '2026-10-05', 'contact_type' => 'email', 'note_text' => 'Sent the tutoring link.',
        ])->assertCreated();

        $this->briefAs('reyes', 'S1002');

        $this->assertCount(2, $this->model->calls);
    }

    public function test_private_notes_are_counted_but_never_sent(): void
    {
        // S1037 has one private note (N041) and one that isn't (N007).
        $this->briefAs('lindqvist', 'S1037')
            ->assertJsonPath('data.notes_used', 1)
            ->assertJsonPath('data.private_left_out', 1);

        $this->assertStringNotContainsString('counselor', $this->model->calls[0]['input']);
    }

    public function test_no_notes_means_no_brief_and_no_call(): void
    {
        $this->briefAs('lindqvist', 'S1018')->assertJsonPath('data', null)->assertJsonPath('meta.reason', 'no_notes');

        $this->assertSame([], $this->model->calls);
    }

    public function test_no_answer_means_the_brief_is_unavailable(): void
    {
        $this->app->instance(JsonModel::class, new FakeJsonModel(null));

        $this->briefAs('reyes', 'S1002')->assertJsonPath('data', null)->assertJsonPath('meta.reason', 'unavailable');
    }

    public function test_the_admin_can_read_a_brief_but_another_advisor_cannot(): void
    {
        $this->briefAs('admin', 'S1002')->assertOk();
        $this->briefAs('okafor', 'S1002')->assertForbidden();
    }

    public function test_marking_a_checked_note_private_drops_what_the_ai_took_from_it(): void
    {
        $this->actingAs(Advisor::where('username', 'reyes')->firstOrFail());
        $student = $this->student('S1008');
        $note = $this->postJson("/api/students/{$student->id}/notes", [
            'contact_date' => '2026-10-05', 'contact_type' => 'email', 'note_text' => 'Asked for a reply about a family matter.',
        ])->json('data.id');

        // As if the AI check set these from the note, and the advisor then
        // accepted a preference from it.
        $student->forceFill([
            'follow_up_on' => '2026-10-12', 'follow_up_reason' => 'Family matter', 'follow_up_note_id' => $note, 'follow_up_set_by' => null,
            'awaiting_reply_since' => '2026-10-05', 'awaiting_reply_note_id' => $note,
        ])->save();
        $this->putJson("/api/students/{$student->id}/contact-preference", ['contact_preference' => 'Text only.', 'note_id' => $note])
            ->assertJsonPath('data.contact_preference.source.note_id', $note);
        ContactNote::whereKey($note)->update(['ai_read' => true]);

        $this->patchJson("/api/notes/{$note}", ['is_private' => true])
            ->assertOk()
            ->assertJsonPath('data.is_private', true)
            ->assertJsonPath('data.ai_read', true)
            ->assertJsonPath('data.edited_label', null);

        $student->refresh();
        $this->assertNull($student->follow_up_reason);
        $this->assertNull($student->awaiting_reply_since);
        $this->assertSame('Text only.', $student->contact_preference);
        $this->assertNull($student->contact_preference_note_id);
    }

    public function test_a_preference_can_only_point_at_a_note_everyone_may_read(): void
    {
        $this->actingAs(Advisor::where('username', 'lindqvist')->firstOrFail());
        $n041 = ContactNote::where('external_id', 'N041')->value('id'); // private

        $this->putJson("/api/students/{$this->student('S1037')->id}/contact-preference", ['contact_preference' => 'Email only.', 'note_id' => $n041])
            ->assertJsonValidationErrors(['note_id']);
    }

    public function test_only_the_author_can_change_a_note(): void
    {
        $note = ContactNote::where('external_id', 'N017')->value('id'); // Reyes's

        $this->actingAs(Advisor::where('username', 'admin')->firstOrFail());
        $this->patchJson("/api/notes/{$note}", ['is_private' => true])->assertForbidden();
    }
}
