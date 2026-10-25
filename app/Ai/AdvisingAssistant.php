<?php

namespace App\Ai;

use App\Enums\ContactType;
use App\Models\AiBrief;
use App\Models\ContactNote;
use App\Models\Student;
use App\Support\Clock;
use App\Support\SensitiveKeywords;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * The app's one AI feature: a prep brief for each student, and a
 * check that runs once on each version of a note an advisor saves. The
 * check returns the brief too, plus what the advisor should be asked
 * (sensitive, contact preference) and what changes in the background
 * (follow-up, waiting on a reply).
 *
 * The AI never reads private notes or the student's name, email, or ID
 * (StudentContext). An answer that doesn't match the schema counts as no
 * answer, and no answer changes nothing.
 */
final class AdvisingAssistant
{
    // How long an answer waits for the advisor to answer "This note may be
    // sensitive" before it is dropped.
    private const PENDING_MINUTES = 30;

    // How long "the AI is unavailable" is remembered for a student, so an
    // outage doesn't cost a waiting call on every page view.
    private const UNAVAILABLE_SECONDS = 60;

    public function __construct(private readonly JsonModel $model) {}

    /**
     * The cached brief, or a new one when what it was built from has
     * changed. Returns ['brief' => array|null, 'reason' => 'ok'|'no_notes'|
     * 'unavailable'].
     */
    public function brief(Student $student): array
    {
        $student->loadMissing(['notes', 'followUpNote', 'brief']);
        $context = StudentContext::for($student);
        $version = StudentContext::versionFor($student);

        if ($context->notesUsed === 0) {
            return ['brief' => null, 'reason' => 'no_notes'];
        }

        if ($student->brief?->notes_version === $version) {
            return ['brief' => $student->brief->content, 'reason' => 'ok'];
        }

        if (Cache::has("brief-unavailable:{$student->id}")) {
            return ['brief' => null, 'reason' => 'unavailable'];
        }

        // One writer per student at a time. A second request for the same
        // brief waits, then finds the one the first request wrote, instead
        // of paying for its own.
        try {
            return Cache::lock("brief:{$student->id}", ClaudeJsonModel::TIMEOUT + 10)->block(ClaudeJsonModel::TIMEOUT + 5, function () use ($student, $context, $version) {
                $cached = $student->brief()->first();

                if ($cached?->notes_version === $version) {
                    return ['brief' => $cached->content, 'reason' => 'ok'];
                }

                $answer = $this->valid($this->model->json(self::system(), $context->input, self::briefSchema()), self::briefRules());

                if ($answer === null) {
                    Cache::put("brief-unavailable:{$student->id}", true, self::UNAVAILABLE_SECONDS);

                    return ['brief' => null, 'reason' => 'unavailable'];
                }

                return ['brief' => $this->saveBrief($student, $answer, $context->meta(), $version)->content, 'reason' => 'ok'];
            });
        } catch (LockTimeoutException) {
            return ['brief' => null, 'reason' => 'unavailable'];
        } catch (QueryException $e) {
            // Only the exception's class: its message holds the query's
            // values, which here include the brief.
            Log::warning('Saving the AI brief failed', ['exception' => $e::class]);

            return ['brief' => null, 'reason' => 'unavailable'];
        }
    }

    /**
     * Check a note the advisor just saved or edited. A private note is
     * never sent, and a note already checked since its last change isn't
     * sent again (each check is a paid call that could undo a hand edit).
     *
     * If the AI thinks the note may be sensitive, nothing it took from the
     * note is applied yet: the advisor answers first, as on the design
     * board. "Mark private" drops it; "Keep as is" applies it (keep()).
     */
    public function checkNote(ContactNote $note): NoteCheck
    {
        if ($note->is_private || $note->checkedSinceLastChange()) {
            return new NoteCheck(ran: false);
        }

        $student = $note->student->load(['notes', 'followUpNote']);
        $context = StudentContext::for($student, $note);

        $answer = $this->valid(
            $this->model->json(self::system().self::checkInstructions(), $context->input, self::checkSchema()),
            self::checkRules(),
        );

        if ($answer === null) {
            return new NoteCheck(ran: false);
        }

        // ai_read lets the note say, if it is made private later, that the
        // AI read it once. Without timestamps, so the note still
        // counts as unchanged.
        ContactNote::withoutTimestamps(fn () => $note->forceFill(['ai_read' => true, 'ai_checked_revision' => $note->revision])->save());

        $suggestion = self::changedPreference($answer['contact_preference_suggestion'], $student->contact_preference);

        // Ask only when the keyword hint didn't already show while the
        // advisor typed, so they aren't asked twice.
        if ($answer['looks_sensitive'] && SensitiveKeywords::find($note->note_text) === []) {
            Cache::put(self::pendingKey($note), ['answer' => $answer, 'meta' => $context->meta()], now()->addMinutes(self::PENDING_MINUTES));

            return new NoteCheck(ran: true, askSensitive: true, contactPreferenceSuggestion: $suggestion);
        }

        return $this->apply($note, $answer, $context->meta(), $suggestion);
    }

    /**
     * The advisor answered "Keep as is" to "This note may be sensitive":
     * apply what the check found.
     */
    public function keep(ContactNote $note): NoteCheck
    {
        $pending = Cache::pull(self::pendingKey($note));

        if ($pending === null || $note->is_private) {
            return new NoteCheck(ran: false);
        }

        return $this->apply($note, $pending['answer'], $pending['meta'], suggestion: null);
    }

    /**
     * The note was made private, so a held answer must never be applied.
     */
    public static function forgetPending(ContactNote $note): void
    {
        Cache::forget(self::pendingKey($note));
    }

    private static function pendingKey(ContactNote $note): string
    {
        return "note-check:{$note->id}";
    }

    private function apply(ContactNote $note, array $answer, array $meta, ?string $suggestion): NoteCheck
    {
        try {
            return DB::transaction(function () use ($note, $answer, $meta, $suggestion) {
                // Read the note again under a lock. If it was made private
                // while the AI was reading it, use nothing from it.
                $fresh = ContactNote::lockForUpdate()->find($note->id);

                if ($fresh === null || $fresh->is_private) {
                    return new NoteCheck(ran: false);
                }

                $student = Student::lockForUpdate()->findOrFail($fresh->student_id)->load(['notes', 'followUpNote']);

                $messages = collect([
                    $this->applyFollowUp($student, $fresh, $answer['follow_up'], $meta['newNoteText']),
                    $this->applyWaiting($student, $fresh, $answer),
                ])->filter()->values()->all();

                // The brief describes the notes the AI read. If a newer note
                // arrived meanwhile, skip it: that note's check, or the next
                // page view, writes a current one.
                $student->load(['notes', 'followUpNote']);

                if (StudentContext::notesSignature($student) === $meta['signature']) {
                    $this->saveBrief($student, $answer['brief'], $meta, StudentContext::versionFor($student));
                }

                return new NoteCheck(ran: true, contactPreferenceSuggestion: $suggestion, messages: $messages);
            });
        } catch (QueryException $e) {
            // Only the exception's class: its message holds the query's
            // values, which here include the note's words and the brief.
            Log::warning('Saving the AI check failed', ['exception' => $e::class]);

            return new NoteCheck(ran: false);
        }
    }

    private function saveBrief(Student $student, array $brief, array $meta, string $version): AiBrief
    {
        return $student->brief()->updateOrCreate([], [
            'content' => [
                'summary' => $brief['summary'],
                'open_items' => $brief['open_items'],
                'suggested_opener' => $brief['suggested_opener'],
                'notes_used' => $meta['notesUsed'],
                'private_left_out' => $meta['privateLeftOut'],
            ],
            'notes_version' => $version,
        ]);
    }

    /**
     * Set or clear the next follow-up from the AI's answer.
     *
     * - The AI must quote the new note. Rationale: without a quote, it once
     *   restated an older note's deadline and credited it to an unrelated
     *   new note, and a newer, unrelated note must not change the follow-up.
     * - "set" never replaces a follow-up the advisor typed in; that one is
     *   theirs to change.
     * - A follow-up taken from a private note is left alone: the AI never
     *   saw its reason.
     */
    private function applyFollowUp(Student $student, ContactNote $note, array $followUp, ?string $noteText): ?string
    {
        if ($student->followUpNote?->is_private || ! self::quotes($noteText, $followUp['evidence'])) {
            return null;
        }

        if ($followUp['action'] === 'set' && filled($followUp['reason']) && $student->follow_up_set_by === null) {
            $date = self::sensibleDate($followUp['date']);

            $student->forceFill([
                'follow_up_on' => $date,
                'follow_up_reason' => Str::limit($followUp['reason'], 200),
                'follow_up_note_id' => $note->id,
                'follow_up_set_by' => null,
                'follow_up_set_at' => Clock::now(),
            ])->save();

            return 'Next follow-up set by AI: '.($date ? $date->format('M j, Y') : $student->follow_up_reason).'.';
        }

        if ($followUp['action'] === 'done' && ($student->follow_up_on !== null || $student->follow_up_reason !== null)) {
            $student->forceFill([
                'follow_up_on' => null,
                'follow_up_reason' => null,
                'follow_up_note_id' => null,
                'follow_up_set_by' => null,
                'follow_up_set_at' => null,
            ])->save();

            return 'Next follow-up cleared by AI: the note says it is done.';
        }

        return null;
    }

    /**
     * Set or clear "waiting on a reply". The rules with no AI ran
     * when the note was saved; this adds what needs reading the new note:
     *
     * - If the new note is outreach still waiting on the student, it marks
     *   the student as waiting, from this note, unless something was
     *   already pending (an earlier unanswered outreach keeps its date).
     * - If the new note shows the student answered, it clears "waiting",
     *   but only for a phone note. Zoom and in person cleared it at save;
     *   email and "Note only" never clear it.
     */
    private function applyWaiting(Student $student, ContactNote $note, array $answer): ?string
    {
        $type = $note->contact_type;

        if ($answer['new_note_awaits_reply'] && $type->isContact() && $student->awaiting_reply_since === null) {
            $student->forceFill(['awaiting_reply_since' => $note->contact_date, 'awaiting_reply_note_id' => $note->id])->save();

            return 'Marked as waiting on a reply.';
        }

        $answeredCall = $answer['new_note_shows_reply']
            && $type === ContactType::Phone
            && $student->awaiting_reply_since !== null
            && $note->contact_date->gte($student->awaiting_reply_since);

        if ($answeredCall) {
            $student->forceFill(['awaiting_reply_since' => null, 'awaiting_reply_note_id' => null])->save();

            return '"Waiting on a reply" cleared: the student answered.';
        }

        return null;
    }

    /**
     * Whether $quote appears in the note, ignoring case, spacing, and the
     * quote marks the AI may wrap it in.
     */
    private static function quotes(?string $noteText, ?string $quote): bool
    {
        $normalize = fn (?string $text) => Str::lower(Str::squish(Str::replaceMatches('/^["\'“”‘’]+|["\'“”‘’]+$/u', '', Str::trim((string) $text))));
        $quote = $normalize($quote);

        return $quote !== '' && Str::contains($normalize($noteText), $quote);
    }

    /**
     * The suggested preference, unless it is empty or only repeats the
     * current one, ignoring case and spacing. Either way there is nothing to
     * ask the advisor.
     */
    private static function changedPreference(?string $suggestion, ?string $current): ?string
    {
        $normalize = fn (?string $text) => Str::lower(Str::squish((string) $text));

        return filled($suggestion) && $normalize($suggestion) !== $normalize($current) ? $suggestion : null;
    }

    /**
     * A follow-up date the AI gave, if it is real and between a year ago
     * and two years ahead. Any other value means "no date".
     */
    private static function sensibleDate(?string $date): ?CarbonImmutable
    {
        if ($date === null || ! Validator::make(['date' => $date], ['date' => 'date_format:Y-m-d'])->passes()) {
            return null;
        }

        $parsed = CarbonImmutable::parse($date);

        return $parsed->between(Clock::today()->subYear(), Clock::today()->addYears(2)) ? $parsed : null;
    }

    /**
     * The answer if it passes the rules, else null. The schema already
     * constrains the model; this is the app's own check.
     */
    private function valid(?array $answer, array $rules): ?array
    {
        if ($answer === null) {
            return null;
        }

        return Validator::make($answer, $rules)->fails() ? null : $answer;
    }

    private static function system(): string
    {
        return <<<'PROMPT'
            You help a university academic advisor prepare to contact a student. You get the student's recent advising notes, the contact preference and next follow-up if there are any, and today's date. The advisor wrote the notes, so "Called" or "Emailed" means the advisor called or emailed. The student's name, email, and ID are replaced with "the student". Some notes are private and were left out; you are told how many.

            Write a short prep brief:
            - summary: 2 short sentences on where things stand. The advisor already sees the student's year, major, GPA, and risk level and the contact preference, so don't repeat them.
            - open_items: up to 3 things to raise or do, each under 15 words.
            - suggested_opener: one or two short, neutral sentences the advisor could say to start the conversation.

            Rules for the brief:
            - Write dates like "Jun 2", not "2026-06-02".
            - Use only facts in the notes. Never make up facts, dates, or deadlines.
            - Never guess what a private note says or hint at it. Private notes may hold newer contacts, so never say when the last contact was, and never say nothing happened after a note's date.
            - Never guess a diagnosis or label the student's mental health.
            - Never judge the student (for example "lazy" or "unmotivated").
            - Never promise outcomes such as aid amounts, waivers, or grade changes, and give no legal, medical, or immigration advice.
            - If there is a contact preference, follow it. Never suggest a contact method that goes against it.
            - Say "the student". Never use a name.
            - Never compare the student with other students.
            - Keep the opener neutral. Don't bring up sensitive topics in it.
            PROMPT;
    }

    private static function checkInstructions(): string
    {
        return <<<'PROMPT'


            The note marked (NEW) was just saved. Also answer these, about the new note only:
            - contact_preference_suggestion: only if the new note itself says how the student wants to be contacted (a channel, a time, or a channel to avoid), the full updated preference in one sentence, keeping what still applies from the current one. Otherwise null.
            - looks_sensitive: true if the new note holds personal information that should be private, such as health, mental health, family, money troubles, relationships, legal matters, immigration, or safety. Otherwise false.
            - new_note_awaits_reply: true if the new note is outreach that still needs the student's answer, for example the advisor asked the student to reply, left a voicemail, or got no response. Otherwise false.
            - new_note_shows_reply: true if the new note shows the student answered or talked with the advisor. Otherwise false.
            - follow_up: "set" if the new note itself names something the advisor must follow up on, with its date (YYYY-MM-DD, or null if the note gives no date) and a short reason of a few words. "done" if the new note shows the current follow-up is finished. "none" otherwise. Something the student will do on their own is not an advisor follow-up. For "set" or "done", evidence is the exact words from the new note that show it; for "none", null.
            PROMPT;
    }

    private static function briefSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'summary' => ['type' => 'string'],
                'open_items' => ['type' => 'array', 'items' => ['type' => 'string']],
                'suggested_opener' => ['type' => 'string'],
            ],
            'required' => ['summary', 'open_items', 'suggested_opener'],
            'additionalProperties' => false,
        ];
    }

    private static function checkSchema(): array
    {
        $nullableString = ['anyOf' => [['type' => 'string'], ['type' => 'null']]];

        return [
            'type' => 'object',
            'properties' => [
                'brief' => self::briefSchema(),
                'contact_preference_suggestion' => $nullableString,
                'looks_sensitive' => ['type' => 'boolean'],
                'new_note_awaits_reply' => ['type' => 'boolean'],
                'new_note_shows_reply' => ['type' => 'boolean'],
                'follow_up' => [
                    'type' => 'object',
                    'properties' => [
                        'action' => ['type' => 'string', 'enum' => ['set', 'done', 'none']],
                        'date' => $nullableString,
                        'reason' => $nullableString,
                        'evidence' => $nullableString,
                    ],
                    'required' => ['action', 'date', 'reason', 'evidence'],
                    'additionalProperties' => false,
                ],
            ],
            'required' => ['brief', 'contact_preference_suggestion', 'looks_sensitive', 'new_note_awaits_reply', 'new_note_shows_reply', 'follow_up'],
            'additionalProperties' => false,
        ];
    }

    private static function briefRules(string $prefix = ''): array
    {
        return [
            "{$prefix}summary" => ['required', 'string', 'max:2000'],
            "{$prefix}open_items" => ['present', 'array', 'max:8'],
            "{$prefix}open_items.*" => ['string', 'max:300'],
            "{$prefix}suggested_opener" => ['required', 'string', 'max:500'],
        ];
    }

    private static function checkRules(): array
    {
        return [
            ...self::briefRules('brief.'),
            'contact_preference_suggestion' => ['nullable', 'string', 'max:500'],
            'looks_sensitive' => ['required', 'boolean'],
            'new_note_awaits_reply' => ['required', 'boolean'],
            'new_note_shows_reply' => ['required', 'boolean'],
            'follow_up.action' => ['required', 'in:set,done,none'],
            'follow_up.date' => ['nullable', 'string'],
            'follow_up.reason' => ['nullable', 'string', 'max:200'],
            'follow_up.evidence' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
