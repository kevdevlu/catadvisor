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
 * The AI check that runs after a note is saved,
 * against a fake model. Nothing here calls the real API.
 */
class NoteCheckTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private FakeJsonModel $model;

    /**
     * Answer the next check with these fields over a dull default answer.
     */
    private function answer(array $fields = []): void
    {
        $this->model = new FakeJsonModel([
            'brief' => ['summary' => 'Where things stand.', 'open_items' => ['Ask about tutoring.'], 'suggested_opener' => 'Hi, how is the term going?'],
            'contact_preference_suggestion' => null,
            'looks_sensitive' => false,
            'awaiting_reply' => 'no',
            'follow_up' => ['action' => 'none', 'date' => null, 'reason' => null],
            ...$fields,
        ]);

        $this->app->instance(JsonModel::class, $this->model);
    }

    private function student(string $sisId): Student
    {
        return Student::where('sis_id', $sisId)->firstOrFail();
    }

    /**
     * Save a note as the given advisor, then run the check on it.
     */
    private function saveAndCheck(string $username, string $sisId, array $fields = []): TestResponse
    {
        $this->actingAs(Advisor::where('username', $username)->firstOrFail());

        $note = $this->postJson("/api/students/{$this->student($sisId)->id}/notes", [
            'contact_date' => '2026-10-05',
            'contact_type' => 'email',
            'note_text' => 'Sent tutoring sign-up information.',
            ...$fields,
        ])->assertCreated();

        return $this->postJson("/api/notes/{$note->json('data.id')}/check")->assertOk();
    }

    public function test_the_check_saves_the_brief_and_a_follow_up_set_by_ai(): void
    {
        $this->answer(['follow_up' => ['action' => 'set', 'date' => '2026-10-20', 'reason' => 'Tutoring form']]);

        $this->saveAndCheck('reyes', 'S1008', ['note_text' => 'Needs to send the tutoring form by Oct 20.'])
            ->assertJsonPath('data.ran', true)
            ->assertJsonPath('data.messages', ['Next follow-up set by AI: Oct 20, 2026.']);

        $student = $this->student('S1008');
        $this->assertSame('2026-10-20', $student->follow_up_on->toDateString());
        $this->assertSame('Tutoring form', $student->follow_up_reason);
        $this->assertNull($student->follow_up_set_by);
        $this->assertSame('Where things stand.', $student->brief->content['summary']);
    }

    public function test_an_unanswered_email_marks_the_student_as_waiting(): void
    {
        $this->answer(['awaiting_reply' => 'yes']);

        $this->saveAndCheck('reyes', 'S1008')->assertJsonPath('data.messages', ['Marked as waiting on a reply.']);

        $this->assertSame('2026-10-05', $this->student('S1008')->awaiting_reply_since->toDateString());
    }

    public function test_an_answered_phone_call_clears_waiting_but_an_email_does_not(): void
    {
        // S1002 has been waiting on a reply since the Jun 2 email.
        $this->answer(['awaiting_reply' => 'no']);

        $this->saveAndCheck('reyes', 'S1002', ['contact_type' => 'email']);
        $this->assertNotNull($this->student('S1002')->awaiting_reply_since);

        $this->saveAndCheck('reyes', 'S1002', ['contact_type' => 'phone', 'note_text' => 'Talked about tutoring times.'])
            ->assertJsonPath('data.messages', ['"Waiting on a reply" cleared: the student answered.']);
        $this->assertNull($this->student('S1002')->awaiting_reply_since);
    }

    public function test_a_missed_call_keeps_the_student_waiting(): void
    {
        $this->answer(['awaiting_reply' => 'yes']);

        $this->saveAndCheck('okafor', 'S1044', ['contact_type' => 'phone', 'note_text' => 'Left voicemail again. No response.']);

        $this->assertSame('2026-06-11', $this->student('S1044')->awaiting_reply_since->toDateString());
    }

    public function test_the_sensitive_question_comes_only_when_the_keyword_hint_did_not_show(): void
    {
        $this->answer(['looks_sensitive' => true]);

        $this->saveAndCheck('reyes', 'S1008', ['note_text' => 'Student was upset about something at home.'])
            ->assertJsonPath('data.ask_sensitive', true);

        // "counselor" shows the keyword hint while typing, so no second question.
        $this->saveAndCheck('reyes', 'S1008', ['note_text' => 'Student is seeing a counselor.'])
            ->assertJsonPath('data.ask_sensitive', false);

        $this->assertTrue(ContactNote::where('note_text', 'Student was upset about something at home.')->value('ai_read'));
    }

    public function test_a_contact_preference_suggestion_comes_back_to_ask_about(): void
    {
        $this->answer(['contact_preference_suggestion' => 'Text only, after 5 pm.']);

        $this->saveAndCheck('reyes', 'S1008', ['note_text' => 'Prefers texts after 5 pm now.'])
            ->assertJsonPath('data.contact_preference_suggestion', 'Text only, after 5 pm.');

        // Asking is all the AI does; the advisor decides.
        $this->assertNull($this->student('S1008')->contact_preference);
    }

    public function test_the_ai_never_reads_private_notes_or_who_the_student_is(): void
    {
        $this->answer(['follow_up' => ['action' => 'done', 'date' => null, 'reason' => null]]);
        $student = $this->student('S1037');

        $this->saveAndCheck('lindqvist', 'S1037', [
            'note_text' => "{$student->first_name} {$student->last_name} ({$student->sis_id}) wrote from {$student->email}.",
        ]);

        $input = $this->model->calls[0]['input'];
        foreach ([$student->first_name, $student->last_name, $student->sis_id, $student->email, 'counselor', 'FAFSA'] as $secret) {
            $this->assertStringNotContainsStringIgnoringCase($secret, $input);
        }
        $this->assertStringContainsString('the student (the student) wrote from the student.', $input);
        $this->assertStringContainsString('Private notes left out: 1', $input);

        // The follow-up came from the private note N041, so the AI can't
        // clear it.
        $this->assertSame('FAFSA verification', $this->student('S1037')->follow_up_reason);
    }

    public function test_a_private_note_is_never_sent(): void
    {
        $this->answer();

        $this->saveAndCheck('reyes', 'S1008', ['is_private' => true])->assertJsonPath('data.ran', false);

        $this->assertSame([], $this->model->calls);
    }

    public function test_no_answer_changes_nothing(): void
    {
        $this->model = new FakeJsonModel(null);
        $this->app->instance(JsonModel::class, $this->model);

        $this->saveAndCheck('reyes', 'S1002')->assertJsonPath('data.ran', false)->assertJsonPath('data.messages', []);

        $this->assertNotNull($this->student('S1002')->awaiting_reply_since);
        $this->assertNull($this->student('S1002')->brief);
    }

    public function test_an_answer_that_breaks_the_rules_counts_as_no_answer(): void
    {
        $this->answer(['awaiting_reply' => 'maybe']);

        $this->saveAndCheck('reyes', 'S1008')->assertJsonPath('data.ran', false);

        $this->assertNull($this->student('S1008')->brief);
    }

    public function test_only_the_notes_author_can_run_the_check(): void
    {
        $this->answer();
        $note = ContactNote::where('external_id', 'N017')->firstOrFail(); // Reyes's

        $this->actingAs(Advisor::where('username', 'admin')->firstOrFail());
        $this->postJson("/api/notes/{$note->id}/check")->assertForbidden();

        $this->actingAs(Advisor::where('username', 'okafor')->firstOrFail());
        $this->postJson("/api/notes/{$note->id}/check")->assertForbidden();
    }

    public function test_without_an_api_key_the_real_model_makes_no_call(): void
    {
        config(['services.anthropic.key' => '']);

        $this->assertNull(app(JsonModel::class)->json('system', 'input', ['type' => 'object']));
    }
}
