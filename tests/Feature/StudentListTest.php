<?php

namespace Tests\Feature;

use App\Models\Advisor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The first screen's list and cards. The
 * expected counts were worked out from the CSVs by a separate script.
 */
class StudentListTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function listAs(string $username, array $query = []): TestResponse
    {
        $this->actingAs(Advisor::where('username', $username)->firstOrFail());

        return $this->getJson('/api/students?'.http_build_query($query));
    }

    private function row(TestResponse $response, string $sisId): array
    {
        return collect($response->json('data'))->firstWhere('sis_id', $sisId);
    }

    public function test_card_counts_for_each_advisor_and_the_admin(): void
    {
        $expected = [
            'reyes' => ['due' => 10, 'high_overdue' => 4, 'waiting' => 2, 'never' => 0, 'recent' => 5],
            'okafor' => ['due' => 4, 'high_overdue' => 2, 'waiting' => 2, 'never' => 0, 'recent' => 10],
            'lindqvist' => ['due' => 4, 'high_overdue' => 1, 'waiting' => 0, 'never' => 3, 'recent' => 7],
            'admin' => ['due' => 18, 'high_overdue' => 7, 'waiting' => 4, 'never' => 3, 'recent' => 22],
        ];

        foreach ($expected as $username => $counts) {
            $this->listAs($username)->assertOk()->assertJsonPath('meta.counts', $counts);
        }
    }

    public function test_the_fourth_card_is_never_contacted_only_when_there_are_any(): void
    {
        $this->listAs('reyes')->assertJsonPath('meta.fourth_card', 'recent');
        $this->listAs('lindqvist')->assertJsonPath('meta.fourth_card', 'never');
    }

    public function test_reyes_sees_only_their_caseload_with_todays_date(): void
    {
        $response = $this->listAs('reyes')
            ->assertJsonCount(17, 'data')
            ->assertJsonPath('meta.caseload', 17)
            ->assertJsonPath('meta.today_label', 'Monday, October 5, 2026');

        $this->assertSame(['Reyes'], collect($response->json('data'))->pluck('advisor')->unique()->values()->all());
    }

    public function test_a_row_carries_the_contact_status(): void
    {
        $jordan = $this->row($this->listAs('reyes'), 'S1002');

        $this->assertSame(-95, $jordan['days_until_due']); // 125 days since contact, target 30
        $this->assertSame('Jun 2, 2026', $jordan['last_contact']['label']);
        $this->assertSame(125, $jordan['last_contact']['days_ago']);
        $this->assertTrue($jordan['is_due']);
        $this->assertTrue($jordan['waiting']);
        $this->assertStringStartsWith('Phone only', $jordan['contact_preference']);
        $this->assertSame(['date_label' => 'Jun 2', 'type' => 'Email', 'is_private' => false], array_diff_key($jordan['last_note'], ['text' => 1]));
    }

    public function test_follow_ups_and_never_contacted_students_count_as_due(): void
    {
        $lindqvist = $this->listAs('lindqvist');

        $camila = $this->row($lindqvist, 'S1037'); // follow-up Oct 15, 10 days away
        $this->assertTrue($camila['follow_up_due']);
        $this->assertTrue($camila['is_due']);
        $this->assertSame(6, $camila['days_until_due']);

        $mateo = $this->row($lindqvist, 'S1018');
        $this->assertTrue($mateo['never_contacted']);
        $this->assertNull($mateo['days_until_due']);
        $this->assertNull($mateo['last_contact']);

        $olivia = $this->row($this->listAs('okafor'), 'S1047'); // follow-up with no date
        $this->assertTrue($olivia['follow_up_due']);
    }

    public function test_cards_and_filters_narrow_the_rows_but_not_the_counts(): void
    {
        $this->listAs('reyes', ['card' => 'waiting'])
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.counts.due', 10);

        $this->listAs('reyes', ['risk' => 'high'])->assertJsonCount(4, 'data');
        $this->listAs('reyes', ['year' => 'Freshman'])->assertJsonCount(3, 'data');
        $this->listAs('reyes', ['card' => 'due', 'risk' => 'low'])->assertJsonCount(3, 'data');
    }

    public function test_search_matches_name_both_ways_id_email_and_major(): void
    {
        foreach (['whit', 'Jordan Whitfield', 'Whitfield, Jordan', 's1002', 'jordan.whitfield@', 'business admin'] as $query) {
            $this->listAs('reyes', ['q' => $query])
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.sis_id', 'S1002');
        }
    }

    public function test_the_last_note_line_hides_a_private_notes_words_from_others(): void
    {
        $camilaForLindqvist = $this->row($this->listAs('lindqvist'), 'S1037'); // last note is private N041
        $this->assertStringContainsString('FAFSA', $camilaForLindqvist['last_note']['text']);

        $camilaForAdmin = $this->row($this->listAs('admin'), 'S1037');
        $this->assertNull($camilaForAdmin['last_note']['text']);
        $this->assertTrue($camilaForAdmin['last_note']['is_private']);
    }

    public function test_bad_filter_values_are_rejected(): void
    {
        $this->listAs('reyes', ['card' => 'everyone'])->assertUnprocessable();
        $this->listAs('reyes', ['risk' => 'extreme'])->assertUnprocessable();
    }

    public function test_the_list_needs_a_signed_in_user(): void
    {
        $this->getJson('/api/students')->assertUnauthorized();
    }
}
