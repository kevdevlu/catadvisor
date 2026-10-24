<?php

namespace Tests\Feature;

use App\Models\Advisor;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * One student's page, with the rules on what each
 * viewer may see.
 */
class StudentDetailTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function showAs(string $username, string $sisId): TestResponse
    {
        $this->actingAs(Advisor::where('username', $username)->firstOrFail());

        return $this->getJson('/api/students/'.Student::where('sis_id', $sisId)->value('id'));
    }

    public function test_an_advisor_sees_their_student_with_where_each_line_came_from(): void
    {
        $response = $this->showAs('reyes', 'S1002')->assertOk();

        $response
            ->assertJsonPath('data.last_name', 'Whitfield')
            ->assertJsonPath('data.credits_earned', 74)
            ->assertJsonPath('data.can_write', true)
            ->assertJsonPath('data.contact_preference.text', 'Phone only, best after 5 pm. Email goes to a shared family account.')
            ->assertJsonPath('data.contact_preference.source.note_name', 'Jan 22, 2026 phone note')
            ->assertJsonPath('data.waiting.since_label', 'Jun 2, 2026')
            ->assertJsonPath('data.waiting.source.note_name', 'Jun 2 email note')
            ->assertJsonPath('data.follow_up', null)
            ->assertJsonPath('data.meta', null)
            ->assertJsonPath('meta.today', '2026-10-05');

        $this->assertSame(['Jun 2, 2026', 'Mar 14, 2026', 'Jan 22, 2026'], collect($response->json('data.notes'))->pluck('date_label')->all());
        $this->assertNotNull($response->json('data.contact_preference.source.note_id'));
    }

    public function test_why_due_lists_each_reason(): void
    {
        $this->showAs('reyes', 'S1002')
            ->assertJsonPath('data.due.is_due', true)
            ->assertJsonPath('data.due.reasons', [
                ['emphasis' => '95 days overdue.', 'text' => 'Target for High risk: every 30 days.'],
                ['emphasis' => null, 'text' => 'No reply since Jun 2, 2026.'],
            ]);

        $this->showAs('lindqvist', 'S1018')
            ->assertJsonPath('data.due.reasons.0', ['emphasis' => 'Never contacted.', 'text' => 'Target for High risk: every 30 days.']);

        $this->showAs('okafor', 'S1047')
            ->assertJsonPath('data.follow_up.date', null)
            ->assertJsonPath('data.follow_up.source.set_by', null)
            ->assertJsonPath('data.due.reasons', [
                ['emphasis' => 'Follow-up due: Withdrawal deadline approaching (no date given).', 'text' => null],
            ]);
    }

    public function test_a_student_who_is_not_due_gets_one_line_saying_when_they_will_be(): void
    {
        $this->showAs('reyes', 'S1024')
            ->assertJsonPath('data.due.is_due', false)
            ->assertJsonPath('data.due.reasons', [
                ['emphasis' => null, 'text' => 'Due in 6 days. Target for Medium risk: every 60 days.'],
            ]);
    }

    public function test_an_advisor_cannot_open_another_advisors_student(): void
    {
        $this->showAs('reyes', 'S1037')->assertForbidden();
    }

    public function test_the_admin_sees_any_student_read_only(): void
    {
        $this->showAs('admin', 'S1002')->assertOk()->assertJsonPath('data.can_write', false);
    }

    public function test_an_address_that_is_not_a_student_is_not_found(): void
    {
        $this->actingAs(Advisor::where('username', 'reyes')->firstOrFail());

        $this->getJson('/api/students/not-a-uuid')->assertNotFound();
        $this->getJson('/api/students/0199a7d2-0000-7000-8000-000000000000')->assertNotFound();
    }

    public function test_a_private_note_and_what_came_from_it_stay_with_its_author(): void
    {
        // N041 is Lindqvist's private note on S1037, and the follow-up came
        // from it: others see when, never what.
        $author = $this->showAs('lindqvist', 'S1037');
        $author->assertJsonPath('data.notes.0.is_private', true)
            ->assertJsonPath('data.follow_up.reason', 'FAFSA verification')
            ->assertJsonPath('data.due.reasons.0.emphasis', 'Follow up by Oct 15, 2026: FAFSA verification.');
        $this->assertStringContainsString('counselor', $author->json('data.notes.0.text'));

        $admin = $this->showAs('admin', 'S1037');
        $admin->assertJsonPath('data.notes.0.text', null)
            ->assertJsonPath('data.notes.0.date_label', 'Sep 11, 2026')
            ->assertJsonPath('data.notes.0.type.label', 'In person')
            ->assertJsonPath('data.follow_up.date_label', 'Oct 15, 2026')
            ->assertJsonPath('data.follow_up.reason', null)
            ->assertJsonPath('data.follow_up.reason_private', true)
            ->assertJsonPath('data.due.reasons.0.emphasis', 'Follow up by Oct 15, 2026 (reason is private).');
        $this->assertStringNotContainsString('counselor', $admin->getContent());
        $this->assertStringNotContainsString('FAFSA', $admin->getContent());
    }

    public function test_the_duplicate_notice_names_no_one(): void
    {
        $response = $this->showAs('okafor', 'S1050')->assertOk()->assertJsonPath('data.possible_duplicate', true);

        $this->assertStringNotContainsString('S1021', $response->getContent());
    }
}
