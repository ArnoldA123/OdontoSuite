<?php

namespace Tests\Unit\Support;

use App\Support\QuotationText;
use Tests\TestCase;

/**
 * #204 — the free-text hygiene of a quotation lives in one place, with the
 * same caps on every writing path.
 */
class QuotationTextTest extends TestCase
{
    public function test_terms_are_stripped_of_markup_and_trimmed(): void
    {
        $this->assertSame(
            'Atención odontológica',
            QuotationText::sanitizeTerms('  <p>Atención <strong>odontológica</strong></p>  ')
        );
    }

    public function test_terms_are_capped_at_two_thousand_characters_without_an_ellipsis(): void
    {
        $sanitized = QuotationText::sanitizeTerms(str_repeat('a', 2500));

        $this->assertSame(2000, mb_strlen($sanitized));
        $this->assertSame(str_repeat('a', 2000), $sanitized);
    }

    public function test_notes_are_capped_at_one_thousand_characters_without_an_ellipsis(): void
    {
        $sanitized = QuotationText::sanitizeNotes(str_repeat('ñ', 1200));

        $this->assertSame(1000, mb_strlen($sanitized));
        $this->assertSame(str_repeat('ñ', 1000), $sanitized);
    }

    public function test_a_null_or_blank_value_stays_empty(): void
    {
        $this->assertNull(QuotationText::sanitizeNotes(null));
        $this->assertNull(QuotationText::sanitizeTerms(null));
        $this->assertNull(QuotationText::sanitizeNotes('   '));
    }

    public function test_a_rejection_reason_appends_within_the_notes_cap_and_never_loses_the_reason(): void
    {
        $reason = str_repeat('r', 500);
        $notes = QuotationText::appendRejection(str_repeat('n', 1000), $reason);

        $this->assertLessThanOrEqual(1000, mb_strlen($notes));
        $this->assertStringContainsString('Rechazado: '.$reason, $notes);
    }

    public function test_a_rejection_on_an_empty_note_stores_only_the_reason(): void
    {
        $notes = QuotationText::appendRejection(null, '<b>Precio</b> alto');

        $this->assertSame('Rechazado: Precio alto', $notes);
    }
}
