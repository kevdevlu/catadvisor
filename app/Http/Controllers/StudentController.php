<?php

namespace App\Http\Controllers;

use App\Ai\AdvisingAssistant;
use App\Enums\Risk;
use App\Enums\Role;
use App\Http\Resources\StudentDetailResource;
use App\Http\Resources\StudentLines;
use App\Http\Resources\StudentRowResource;
use App\Models\Advisor;
use App\Models\Student;
use App\Support\CaseloadEntry;
use App\Support\Clock;
use App\Support\ContactStatus;
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
            'advisor' => ['nullable', Rule::exists('advisors', 'username')->where('role', Role::Advisor->value)],
        ]);

        $user = $request->user();

        // Only the admin picks an advisor. An advisor always gets
        // their own students, whatever the address says.
        $advisor = $user->isAdmin() ? ($filters['advisor'] ?? null) : null;

        $entries = Student::visibleTo($user)
            ->when($advisor, fn ($query, $username) => $query->whereRelation('advisor', 'username', $username))
            ->with(['notes', 'advisor', ...StudentLines::RELATIONS])
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

        $meta = [
            'today' => Clock::today()->toDateString(),
            'today_label' => Clock::today()->format('l, F j, Y'),
            'caseload' => $entries->count(),
            'counts' => $counts,
            // The fourth card shows never-contacted students when there
            // are any, and recent contacts otherwise.
            'fourth_card' => $counts['never'] > 0 ? 'never' : 'recent',
            // The numbers behind the rules. The "Contact due" help and the
            // card text show these, so a change in Risk::targetDays() or
            // ContactStatus reaches the screen too.
            'rules' => [
                'targets' => collect(Risk::cases())->map(fn (Risk $risk) => [
                    'value' => $risk->value,
                    'label' => $risk->label(),
                    'days' => $risk->targetDays(),
                ]),
                'follow_up_window_days' => ContactStatus::FOLLOW_UP_WINDOW_DAYS,
                'recent_days' => ContactStatus::RECENT_DAYS,
            ],
        ];

        // The admin's "Advisor" menu: its choices, and who is picked.
        if ($user->isAdmin()) {
            $advisors = Advisor::where('role', Role::Advisor)->orderBy('display_name')->get(['username', 'display_name']);

            $meta['advisors'] = $advisors;
            $meta['advisor'] = $advisors->firstWhere('username', $advisor)?->display_name;
        }

        return StudentRowResource::collection($rows)->additional(['meta' => $meta])->response();
    }

    /**
     * One student's page. The route checks that the viewer may
     * see this student.
     */
    public function show(Student $student): StudentDetailResource
    {
        return StudentDetailResource::for($student);
    }

    /**
     * The AI prep brief: the cached one, or a new one if the
     * notes changed since. "reason" says why there is none: no notes yet,
     * or the AI is unavailable (no key, or the call failed).
     */
    public function brief(Student $student, AdvisingAssistant $assistant): JsonResponse
    {
        ['brief' => $brief, 'reason' => $reason] = $assistant->brief($student);

        return response()->json(['data' => $brief, 'meta' => ['reason' => $reason]]);
    }
}
