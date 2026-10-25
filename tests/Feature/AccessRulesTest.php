<?php

namespace Tests\Feature;

use App\Models\Advisor;
use App\Models\ContactNote;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Who may see and change what.
 */
class AccessRulesTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function advisor(string $username): Advisor
    {
        return Advisor::where('username', $username)->firstOrFail();
    }

    private function student(string $sisId): Student
    {
        return Student::where('sis_id', $sisId)->firstOrFail();
    }

    public function test_an_advisor_sees_only_their_own_students(): void
    {
        foreach (['reyes' => 17, 'okafor' => 17, 'lindqvist' => 16] as $username => $caseload) {
            $advisor = $this->advisor($username);
            $visible = Student::visibleTo($advisor)->pluck('advisor_id');

            $this->assertCount($caseload, $visible);
            $this->assertSame([$advisor->id], $visible->unique()->values()->all());
        }

        $this->assertSame(50, Student::visibleTo($this->advisor('admin'))->count());
    }

    public function test_viewing_a_student_is_limited_to_their_advisor_and_the_admin(): void
    {
        $jordan = $this->student('S1002'); // Reyes's student

        $this->assertTrue($this->advisor('reyes')->can('view', $jordan));
        $this->assertTrue($this->advisor('admin')->can('view', $jordan));
        $this->assertFalse($this->advisor('okafor')->can('view', $jordan));
    }

    public function test_the_student_page_says_whose_student_it_is(): void
    {
        $this->actingAs($this->advisor('admin'))
            ->getJson("/api/students/{$this->student('S1002')->id}")
            ->assertJsonPath('data.advisor', 'Reyes');
    }

    public function test_the_admin_is_read_only(): void
    {
        $jordan = $this->student('S1002');

        $this->assertTrue($this->advisor('reyes')->can('update', $jordan));
        $this->assertFalse($this->advisor('admin')->can('update', $jordan));
        $this->assertFalse($this->advisor('okafor')->can('update', $jordan));
    }

    public function test_only_the_author_reads_a_private_notes_words(): void
    {
        $n041 = ContactNote::where('external_id', 'N041')->firstOrFail(); // private, by Lindqvist

        $this->assertTrue($this->advisor('lindqvist')->can('readText', $n041));
        $this->assertFalse($this->advisor('admin')->can('readText', $n041));
        $this->assertFalse($this->advisor('okafor')->can('readText', $n041));

        $n001 = ContactNote::where('external_id', 'N001')->firstOrFail(); // not private
        $this->assertTrue($this->advisor('admin')->can('readText', $n001));
    }

    public function test_another_advisors_notes_stay_unreadable_even_when_not_private(): void
    {
        $n001 = ContactNote::where('external_id', 'N001')->firstOrFail(); // Reyes's student, not private

        $this->assertFalse($this->advisor('okafor')->can('readText', $n001));
    }

    public function test_only_the_author_edits_or_deletes_a_note(): void
    {
        $n001 = ContactNote::where('external_id', 'N001')->firstOrFail(); // by Reyes

        $this->assertTrue($this->advisor('reyes')->can('update', $n001));
        $this->assertTrue($this->advisor('reyes')->can('delete', $n001));
        $this->assertFalse($this->advisor('admin')->can('update', $n001));
        $this->assertFalse($this->advisor('okafor')->can('delete', $n001));
    }
}
