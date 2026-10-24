<?php

namespace Tests\Unit;

use App\Support\Ssn;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SsnTest extends TestCase
{
    public static function ssnForms(): array
    {
        return [
            'dashes (as in N041)' => ['she gave her SSN 000-12-3456 so I could'],
            'spaces' => ['SSN 000 12 3456'],
            'dots' => ['SSN 000.12.3456'],
            'no separators' => ['SSN 000123456'],
            'right after a colon' => ['SSN:000-12-3456'],
            'right after letters' => ['SSN000-12-3456'],
            'en dashes, as pasted from Word' => ['SSN 000–12–3456'],
            'em dashes' => ['SSN 000—12—3456'],
        ];
    }

    #[DataProvider('ssnForms')]
    public function test_removes_an_ssn_in_any_common_form(string $text): void
    {
        $redacted = Ssn::redact($text);

        $this->assertStringContainsString(Ssn::REPLACEMENT, $redacted);
        $this->assertDoesNotMatchRegularExpression('/\d{4}/', $redacted);
    }

    public function test_leaves_phone_numbers_and_dates_alone(): void
    {
        foreach (['Call 520-621-1234 after 5 pm', 'Call 5206211234', 'Met on 2026-09-18', 'Card 4111 1111 1111 1111'] as $text) {
            $this->assertSame($text, Ssn::redact($text));
        }
    }
}
