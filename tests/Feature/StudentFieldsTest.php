<?php

namespace Tests\Feature;

use App\Models\Advisor;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The advisor's own edits to the contact preference, follow-up, and
 * "waiting on a reply".
 */
class StudentFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private string $url;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(Advisor::where('username', 'reyes')->firstOrFail());
        $this->url = '/api/students/'.Student::where('sis_id', 'S1002')->value('id');
    }

    public function test_setting_a_follow_up_records_who_set_it(): void
    {
        $this->putJson("{$this->url}/follow-up", ['date' => '2026-10-12', 'reason' => 'Tutoring form'])
            ->assertOk()
            ->assertJsonPath('data.follow_up.date_label', 'Oct 12, 2026')
            ->assertJsonPath('data.follow_up.reason', 'Tutoring form')
            ->assertJsonPath('data.follow_up.source.set_by', 'Reyes')
            ->assertJsonPath('data.follow_up.source.note_id', null)
            ->assertJsonPath('data.due.reasons.2.emphasis', 'Follow up by Oct 12, 2026: Tutoring form.');
    }

    public function test_a_follow_up_needs_a_date_or_a_reason(): void
    {
        $this->putJson("{$this->url}/follow-up", ['date' => null, 'reason' => ''])
            ->assertJsonValidationErrors(['reason' => 'Add a date or a reason.']);

        $this->putJson("{$this->url}/follow-up", ['date' => '2026-11-01'])
            ->assertOk()
            ->assertJsonPath('data.follow_up.reason', 'Follow up');
    }

    public function test_clearing_the_follow_up_and_waiting_on_a_reply(): void
    {
        $this->putJson("{$this->url}/follow-up", ['reason' => 'Tutoring form'])->assertOk();

        $this->deleteJson("{$this->url}/follow-up")->assertOk()->assertJsonPath('data.follow_up', null);
        $this->deleteJson("{$this->url}/waiting")->assertOk()->assertJsonPath('data.waiting', null);
    }

    public function test_editing_the_contact_preference_by_hand(): void
    {
        $this->putJson("{$this->url}/contact-preference", ['contact_preference' => 'Text only. SSN 123456789 is not needed.'])
            ->assertOk()
            ->assertJsonPath('data.contact_preference.text', 'Text only. SSN [SSN removed] is not needed.')
            ->assertJsonPath('data.contact_preference.source.set_by', 'Reyes')
            ->assertJsonPath('data.contact_preference.source.set_on', 'Oct 5, 2026')
            ->assertJsonPath('data.contact_preference.source.note_id', null);

        $this->putJson("{$this->url}/contact-preference", ['contact_preference' => ''])
            ->assertOk()
            ->assertJsonPath('data.contact_preference', null);
    }

    public function test_a_follow_up_is_due_from_14_days_away(): void
    {
        // S1024 is otherwise not due: last contact Aug 12, Medium risk.
        $url = '/api/students/'.Student::where('sis_id', 'S1024')->value('id');

        $this->putJson("{$url}/follow-up", ['date' => '2026-10-20', 'reason' => 'Check in'])->assertJsonPath('data.due.is_due', false);
        $this->putJson("{$url}/follow-up", ['date' => '2026-10-19', 'reason' => 'Check in'])->assertJsonPath('data.due.is_due', true);
        $this->putJson("{$url}/follow-up", ['date' => '2026-10-01', 'reason' => 'Check in'])->assertJsonPath('data.due.is_due', true);
    }

    public function test_changing_only_the_date_keeps_a_private_reason_private(): void
    {
        // S1037's follow-up came from N041, Lindqvist's private note.
        $url = '/api/students/'.Student::where('sis_id', 'S1037')->value('id');
        $lindqvist = Advisor::where('username', 'lindqvist')->firstOrFail();
        $admin = Advisor::where('username', 'admin')->firstOrFail();

        $this->actingAs($lindqvist)->putJson("{$url}/follow-up", ['date' => '2026-10-16', 'reason' => 'FAFSA verification'])->assertOk();
        $this->app['auth']->forgetGuards();
        $this->actingAs($admin)->getJson($url)
            ->assertJsonPath('data.follow_up.date_label', 'Oct 16, 2026')
            ->assertJsonPath('data.follow_up.reason', null);

        // A new reason is the advisor's own words, so everyone sees it.
        $this->actingAs($lindqvist)->putJson("{$url}/follow-up", ['date' => '2026-10-16', 'reason' => 'Bring the signed form'])->assertOk();
        $this->actingAs($admin)->getJson($url)->assertJsonPath('data.follow_up.reason', 'Bring the signed form');
    }

    public function test_ssns_are_removed_from_the_follow_up_reason(): void
    {
        $this->putJson("{$this->url}/follow-up", ['reason' => 'Verify SSN 123-45-6789 with registrar'])
            ->assertJsonPath('data.follow_up.reason', 'Verify SSN [SSN removed] with registrar');
    }

    public function test_follow_up_dates_must_be_sensible(): void
    {
        $this->putJson("{$this->url}/follow-up", ['date' => '9999-12-31', 'reason' => 'x'])
            ->assertJsonValidationErrors(['date' => 'Pick a date within the next two years.']);
    }

    public function test_a_preference_request_without_the_field_changes_nothing(): void
    {
        $this->putJson("{$this->url}/contact-preference", [])->assertJsonValidationErrors(['contact_preference']);

        $this->assertNotNull(Student::where('sis_id', 'S1002')->value('contact_preference'));
    }

    public function test_the_admin_cannot_change_anything(): void
    {
        $this->actingAs(Advisor::where('username', 'admin')->firstOrFail());

        $this->putJson("{$this->url}/follow-up", ['reason' => 'x'])->assertForbidden();
        $this->deleteJson("{$this->url}/follow-up")->assertForbidden();
        $this->deleteJson("{$this->url}/waiting")->assertForbidden();
        $this->putJson("{$this->url}/contact-preference", ['contact_preference' => 'x'])->assertForbidden();
    }
}
