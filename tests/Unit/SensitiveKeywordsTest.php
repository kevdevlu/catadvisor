<?php

namespace Tests\Unit;

use App\Support\SensitiveKeywords;
use Tests\TestCase;

/**
 * The sensitive-topic hint.
 */
class SensitiveKeywordsTest extends TestCase
{
    public function test_of_the_57_dataset_notes_only_n041_gets_the_hint(): void
    {
        $file = fopen(base_path('advisor_tracker_dataset/contact_notes.csv'), 'r');
        $header = fgetcsv($file, escape: '');
        $flagged = [];

        while (($row = fgetcsv($file, escape: '')) !== false) {
            $note = collect($header)->combine($row);

            if (SensitiveKeywords::find($note['note_text']) !== []) {
                $flagged[] = $note['note_id'];
            }
        }

        fclose($file);

        $this->assertSame(['N041'], $flagged);
    }

    public function test_it_names_the_words_it_found(): void
    {
        $this->assertSame(
            ['counselor', 'anxiety', 'stay private'],
            SensitiveKeywords::find('Seeing a campus counselor for anxiety; asked that this stay private.'),
        );
    }

    public function test_it_catches_wordings_that_earlier_lists_missed(): void
    {
        // The last two use a curly apostrophe, as Word and Outlook type it.
        foreach (['Her dad lost job last month.', 'There is sexual tension with a lab partner.', 'Fight with her boyfriend.', 'This is confidential.', 'Please don’t share this.', 'They can’t afford rent.'] as $text) {
            $this->assertNotSame([], SensitiveKeywords::find($text), $text);
        }
    }

    public function test_it_skips_common_advising_words_that_look_close(): void
    {
        foreach (['Went to orientation week.', 'Domestic student, in-state tuition.', 'Courtesy call before the term.', 'Studied for the GRE.', 'On academic probation.', 'Met with a lab partner.'] as $text) {
            $this->assertSame([], SensitiveKeywords::find($text), $text);
        }
    }
}
