<?php

namespace App\Http\Controllers;

use App\Enums\Risk;
use App\Http\Resources\StudentDetailResource;
use App\Http\Resources\StudentRowResource;
use App\Models\Student;
use App\Support\CaseloadEntry;
use App\Support\Clock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public const CLASS_YEARS = ['Freshman', 'Sophomore', 'Junior', 'Senior'];

    /**
     * The first screen's list: the signed-in person's students
     * with their contact status, plus the four card counts. The filters
     * narrow the rows; the counts always cover the whole caseload.
     *
     * A caseload is small (about 17 students, 50 for the admin), so the rows
     * are worked out in PHP after one query rather than in SQL.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'risk' => ['nullable', Rule::enum(Risk::class)],
            'year' => ['nullable', Rule::in(self::CLASS_YEARS)],
            'card' => ['nullable', Rule::in(CaseloadEntry::CARDS)],
        ]);

        $entries = Student::visibleTo($request->user())
            ->with(['notes', 'advisor'])
            ->get()
            ->map(fn (Student $student) => CaseloadEntry::for($student));

        $rows = $entries
            ->when($filters['card'] ?? null, fn ($rows, $card) => $rows->filter(fn (CaseloadEntry $entry) => $entry->matchesCard($card)))
            ->when($filters['risk'] ?? null, fn ($rows, $risk) => $rows->filter(fn (CaseloadEntry $entry) => $entry->student->risk_flag->value === $risk))
            ->when($filters['year'] ?? null, fn ($rows, $year) => $rows->filter(fn (CaseloadEntry $entry) => $entry->student->class_year === $year))
            ->when($filters['q'] ?? null, fn ($rows, $query) => $rows->filter(fn (CaseloadEntry $entry) => $entry->matchesSearch($query)))
            ->values();

        $counts = collect(CaseloadEntry::CARDS)
            ->mapWithKeys(fn (string $card) => [$card => $entries->filter(fn (CaseloadEntry $entry) => $entry->matchesCard($card))->count()]);

        return StudentRowResource::collection($rows)
            ->additional(['meta' => [
                'today' => Clock::today()->toDateString(),
                'today_label' => Clock::today()->format('l, F j, Y'),
                'caseload' => $entries->count(),
                'counts' => $counts,
                // The fourth card shows never-contacted students when there
                // are any, and recent contacts otherwise.
                'fourth_card' => $counts['never'] > 0 ? 'never' : 'recent',
            ]])
            ->response();
    }

    /**
     * One student's page. The route checks that the viewer may
     * see this student.
     */
    public function show(Student $student): StudentDetailResource
    {
        return StudentDetailResource::for($student);
    }
}
