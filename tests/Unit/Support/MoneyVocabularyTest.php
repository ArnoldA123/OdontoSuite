<?php

namespace Tests\Unit\Support;

use App\Support\MoneyVocabulary;
use Tests\TestCase;

/**
 * #287, D4/D8 — the money vocabulary is one source of truth: which types can be
 * written, which of them move the drawer, which need an administrator behind
 * them and how each one moves the patient balance.
 *
 * `discount` stays in the vocabulary as the field of a charge, never as a
 * transaction type, and `income`/`expense` are legacy column values with no
 * balance meaning.
 */
class MoneyVocabularyTest extends TestCase
{
    public function test_only_the_four_money_types_can_be_written(): void
    {
        $this->assertSame(
            ['payment', 'refund', 'write_off', 'adjustment'],
            MoneyVocabulary::CREATABLE
        );

        foreach (MoneyVocabulary::CREATABLE as $type) {
            $this->assertTrue(MoneyVocabulary::isCreatable($type), "$type must be creatable");
        }

        foreach (['discount', 'income', 'expense', 'transfer', ''] as $type) {
            $this->assertFalse(MoneyVocabulary::isCreatable($type), "$type must not be creatable");
        }
    }

    public function test_only_payment_and_refund_move_the_drawer(): void
    {
        $this->assertSame(['payment'], MoneyVocabulary::CASH_IN);
        $this->assertSame(['refund'], MoneyVocabulary::CASH_OUT);
        $this->assertSame(['write_off', 'adjustment'], MoneyVocabulary::NON_CASH);

        $this->assertTrue(MoneyVocabulary::movesCash('payment'));
        $this->assertTrue(MoneyVocabulary::movesCash('refund'));

        foreach (['write_off', 'adjustment', 'discount', 'income', 'expense'] as $type) {
            $this->assertFalse(MoneyVocabulary::movesCash($type), "$type must not move the drawer");
        }
    }

    public function test_the_cash_movement_follows_the_type(): void
    {
        $this->assertSame('income', MoneyVocabulary::cashMovementType('payment'));
        $this->assertSame('expense', MoneyVocabulary::cashMovementType('refund'));

        foreach (['write_off', 'adjustment', 'discount', 'income', 'expense'] as $type) {
            $this->assertNull(
                MoneyVocabulary::cashMovementType($type),
                "$type never derives a cash movement"
            );
        }
    }

    public function test_refund_write_off_and_adjustment_require_an_authorization(): void
    {
        $this->assertSame(
            ['refund', 'write_off', 'adjustment'],
            MoneyVocabulary::REQUIRES_AUTHORIZATION
        );

        $this->assertFalse(MoneyVocabulary::requiresAuthorization('payment'));

        foreach (MoneyVocabulary::REQUIRES_AUTHORIZATION as $type) {
            $this->assertTrue(MoneyVocabulary::requiresAuthorization($type));
        }
    }

    public function test_the_balance_effect_carries_the_sign(): void
    {
        $this->assertEqualsWithDelta(-100.0, MoneyVocabulary::balanceEffect('payment', 100.0), 0.001);
        $this->assertEqualsWithDelta(100.0, MoneyVocabulary::balanceEffect('refund', 100.0), 0.001);
        $this->assertEqualsWithDelta(-100.0, MoneyVocabulary::balanceEffect('write_off', 100.0), 0.001);
        $this->assertEqualsWithDelta(100.0, MoneyVocabulary::balanceEffect('adjustment', 100.0), 0.001);
        $this->assertEqualsWithDelta(-100.0, MoneyVocabulary::balanceEffect('discount', 100.0), 0.001);
    }

    public function test_a_signed_adjustment_moves_the_balance_in_its_own_direction(): void
    {
        $this->assertEqualsWithDelta(-45.5, MoneyVocabulary::balanceEffect('adjustment', -45.5), 0.001);
        $this->assertEqualsWithDelta(-12.34, MoneyVocabulary::balanceEffect('payment', '12.34'), 0.001);
    }

    public function test_a_type_outside_the_vocabulary_has_no_balance_effect(): void
    {
        foreach (['income', 'expense', 'transfer', ''] as $type) {
            $this->assertEqualsWithDelta(
                0.0,
                MoneyVocabulary::balanceEffect($type, 100.0),
                0.001,
                "$type must not move the patient balance"
            );
        }
    }

    public function test_every_type_of_the_vocabulary_has_its_own_readable_label(): void
    {
        $labels = [];

        foreach ([...MoneyVocabulary::CREATABLE, MoneyVocabulary::DISCOUNT] as $type) {
            $label = MoneyVocabulary::label($type);

            $this->assertIsString($label);
            $this->assertNotSame('', trim($label));
            $labels[$type] = $label;
        }

        $this->assertCount(
            count($labels),
            array_unique($labels),
            'Two money types cannot share a label: the UI would tell them apart by nothing.'
        );
    }
}
