<?php

namespace Tests\Feature;

use App\Models\Advisor;
use App\Models\ContactNote;
use App\Models\Student;
use App\Support\Ssn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Checks the seeded data against known quirks of the dataset.
 */
class DatasetImportTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_imports_every_account_student_and_note(): void
    {
        $this->assertSame(4, Advisor::count());
        $this->assertSame(50, Student::count());
        $this->assertSame(57, ContactNote::count());
    }

    public function test_keeps_each_advisors_caseload(): void
    {
        $counts = Student::with('advisor')->get()->countBy(fn (Student $s) => $s->advisor->display_name);

        $this->assertSame(['Lindqvist' => 16, 'Okafor' => 17, 'Reyes' => 17], $counts->sortKeys()->all());
    }

    public function test_removes_the_ssn_and_marks_n041_private(): void
    {
        $note = ContactNote::where('external_id', 'N041')->firstOrFail();

        $this->assertTrue($note->is_private);
        $this->assertStringNotContainsString('000-12-3456', $note->note_text);
        $this->assertStringContainsString(Ssn::REPLACEMENT, $note->note_text);
        $this->assertSame(1, ContactNote::where('is_private', true)->count());
    }

    public function test_never_contacted_students_have_no_notes(): void
    {
        foreach (['S1006', 'S1018', 'S1040'] as $sisId) {
            $this->assertSame(0, Student::where('sis_id', $sisId)->firstOrFail()->notes()->count());
        }
    }

    public function test_flags_the_okonkwo_records_as_possible_duplicates(): void
    {
        $grace = Student::where('sis_id', 'S1021')->firstOrFail();
        $gracie = Student::where('sis_id', 'S1050')->firstOrFail();

        $this->assertSame($gracie->id, $grace->possible_duplicate_of);
        $this->assertSame($grace->id, $gracie->possible_duplicate_of);
        $this->assertSame(2, Student::whereNotNull('possible_duplicate_of')->count());
    }

    public function test_sets_the_background_fields_from_their_source_notes(): void
    {
        $jordan = Student::where('sis_id', 'S1002')->firstOrFail();
        $this->assertStringStartsWith('Phone only', $jordan->contact_preference);
        $this->assertSame('2026-06-02', $jordan->awaiting_reply_since->toDateString());

        $this->assertSame(4, Student::whereNotNull('awaiting_reply_since')->count());

        $camila = Student::where('sis_id', 'S1037')->firstOrFail();
        $this->assertSame('2026-10-15', $camila->follow_up_on->toDateString());
        $this->assertNotNull($camila->follow_up_set_by);

        $olivia = Student::where('sis_id', 'S1047')->firstOrFail();
        $this->assertNull($olivia->follow_up_on);
        $this->assertNull($olivia->follow_up_set_by);
    }

    public function test_today_is_pinned_to_the_dataset_date(): void
    {
        $this->assertSame('2026-10-05', now()->toDateString());
    }
}
