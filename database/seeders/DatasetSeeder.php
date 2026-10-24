<?php

namespace Database\Seeders;

use App\Enums\ContactType;
use App\Enums\Risk;
use App\Models\Advisor;
use App\Models\ContactNote;
use App\Models\Student;
use App\Support\DatasetDate;
use App\Support\Ssn;
use Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class DatasetSeeder extends Seeder
{
    /**
     * Load the 50 students and 57 notes in advisor_tracker_dataset/.
     */
    public function run(): void
    {
        $advisorIds = Advisor::pluck('id', 'display_name');

        $studentIds = $this->importStudents($advisorIds);
        $this->importNotes($studentIds, $advisorIds);
        $this->flagPossibleDuplicates();
    }

    /**
     * @return array<string, string> student UUIDs keyed by SIS ID (S1001...)
     */
    private function importStudents(Collection $advisorIds): array
    {
        $studentIds = [];

        foreach ($this->rows('students.csv') as $row) {
            // last_contact_date is not imported. It always matches the newest
            // note, so the app works it out from notes.
            $student = Student::create([
                'sis_id' => $row['student_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'email' => $row['email'],
                'major' => $row['major'],
                'class_year' => $row['class_year'],
                'credits_earned' => (int) $row['credits_earned'],
                'gpa' => $row['gpa'],
                'risk_flag' => Risk::fromDataset($row['risk_flag']),
                'advisor_id' => $advisorIds[$row['advisor']],
            ]);

            $studentIds[$student->sis_id] = $student->id;
        }

        return $studentIds;
    }

    /**
     * @param  array<string, string>  $studentIds
     */
    private function importNotes(array $studentIds, Collection $advisorIds): void
    {
        foreach ($this->rows('contact_notes.csv') as $row) {
            $date = DatasetDate::parse($row['contact_date']);

            ContactNote::create([
                'external_id' => $row['note_id'],
                'student_id' => $studentIds[$row['student_id']],
                'author_id' => $advisorIds[$row['advisor']],
                'contact_date' => $date,
                'contact_type' => ContactType::fromLabel($row['contact_type']),
                'note_text' => Ssn::redact($row['note_text']),
                // N041 holds a mental-health disclosure the student asked to
                // keep private. In the app, only a
                // note's author marks it private.
                'is_private' => $row['note_id'] === 'N041',
                'created_at' => $date,
                'updated_at' => $date,
            ]);
        }
    }

    /**
     * Flag students who share a last name, major, class year, and GPA as
     * possible duplicates of each other. In this
     * dataset that is S1021 Grace and S1050 Gracie Okonkwo. They are only
     * flagged, never merged.
     */
    private function flagPossibleDuplicates(): void
    {
        Student::all()
            ->groupBy(fn (Student $student) => implode('|', [
                strtolower($student->last_name),
                $student->major,
                $student->class_year,
                $student->gpa,
            ]))
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->each(function (Collection $group) {
                foreach ($group as $student) {
                    $other = $group->firstWhere('id', '!=', $student->id);
                    $student->update(['possible_duplicate_of' => $other->id]);
                }
            });
    }

    /**
     * Read a dataset CSV as one array per row, keyed by the header line.
     */
    private function rows(string $file): Generator
    {
        $handle = fopen(base_path("advisor_tracker_dataset/{$file}"), 'r');
        $header = fgetcsv($handle, escape: '');

        while (($values = fgetcsv($handle, escape: '')) !== false) {
            yield array_combine($header, $values);
        }

        fclose($handle);
    }
}
