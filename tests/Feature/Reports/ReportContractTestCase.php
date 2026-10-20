<?php

namespace Tests\Feature\Reports;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\DentalChair;
use App\Models\Patient;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Shared fixtures for the Business Intelligence report contract tests
 * (odd/tasks/business-intelligence-ui-redesign.md, Contract v2).
 *
 * Runs against the MySQL harness: RefreshDatabase rebuilds every table, so
 * SQLite is not an option (AGENTS.md §6).
 */
abstract class ReportContractTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * Keeps unique columns (email, username, chair code, transaction number)
     * collision free even when several fixtures are created in one test.
     */
    private static int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Carbon::setTestNow();

        // Capacity is clinic-wide: any chair another suite test left active
        // would change every availability assertion. Start from zero, then let
        // each test create the chairs it expects.
        DentalChair::query()->update(['is_active' => false]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function user(string $role = 'odontologo', ?string $name = null, bool $active = true): User
    {
        $sequence = ++self::$sequence;

        return User::create([
            'name' => $name ?? 'Report '.$role.' '.$sequence,
            'email' => "report.{$role}.{$sequence}@example.com",
            'username' => "report_{$role}_{$sequence}",
            'password' => bcrypt('password'),
            'role' => $role,
            'is_active' => $active,
        ]);
    }

    protected function admin(): User
    {
        return $this->user('administrador');
    }

    protected function patient(bool $active = true): Patient
    {
        $sequence = ++self::$sequence;

        return Patient::create([
            'first_name' => 'Report',
            'last_name' => 'Patient '.$sequence,
            'email' => "report.patient.{$sequence}@example.com",
            'phone' => '+51 9'.str_pad((string) (10000000 + $sequence), 8, '0', STR_PAD_LEFT),
            'is_active' => $active,
        ]);
    }

    protected function appointmentType(float $price = 25000.00): AppointmentType
    {
        $sequence = ++self::$sequence;

        return AppointmentType::create([
            'name' => 'Control '.$sequence,
            'default_duration_minutes' => 60,
            'price' => $price,
            'requires_confirmation' => true,
            'is_active' => true,
        ]);
    }

    protected function chair(bool $active = true): DentalChair
    {
        $sequence = ++self::$sequence;

        return DentalChair::create([
            'name' => 'Chair '.$sequence,
            'code' => 'RC'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            'status' => 'active',
            'is_active' => $active,
        ]);
    }

    protected function paymentMethod(): PaymentMethod
    {
        $sequence = ++self::$sequence;

        return PaymentMethod::create([
            'name' => 'Cash '.$sequence,
            'code' => 'PM'.$sequence,
            'is_active' => true,
        ]);
    }

    protected function appointment(
        User $professional,
        Patient $patient,
        AppointmentType $type,
        DentalChair $chair,
        Carbon|string $scheduledAt,
        string $status = 'completed',
        int $durationMinutes = 60,
        ?User $createdBy = null,
    ): Appointment {
        $start = $scheduledAt instanceof Carbon ? $scheduledAt->copy() : Carbon::parse($scheduledAt);

        return Appointment::create([
            'patient_id' => $patient->id,
            'user_id' => $professional->id,
            'dental_chair_id' => $chair->id,
            'appointment_type_id' => $type->id,
            'scheduled_at' => $start,
            'ends_at' => $start->copy()->addMinutes($durationMinutes),
            'duration_minutes' => $durationMinutes,
            'status' => $status,
            'created_by' => ($createdBy ?? $professional)->id,
        ]);
    }

    protected function payment(
        User $createdBy,
        Patient $patient,
        float $amount,
        Carbon|string $createdAt,
        ?Appointment $appointment = null,
        string $status = 'completed',
        string $type = 'payment',
    ): Transaction {
        $sequence = ++self::$sequence;
        $moment = $createdAt instanceof Carbon ? $createdAt->copy() : Carbon::parse($createdAt);

        $transaction = Transaction::create([
            'patient_id' => $patient->id,
            'appointment_id' => $appointment?->id,
            'payment_method_id' => $this->paymentMethod()->id,
            'created_by' => $createdBy->id,
            'transaction_number' => 'TXN-'.str_pad((string) $sequence, 8, '0', STR_PAD_LEFT),
            'type' => $type,
            'amount' => $amount,
            'status' => $status,
            'processed_at' => $moment,
        ]);

        // `created_at` is not mass assignable, so the period would otherwise be
        // the frozen test clock for every payment and the fixtures would never
        // exercise the [startOfDay, endOfDay] boundaries.
        $transaction->timestamps = false;
        $transaction->forceFill(['created_at' => $moment, 'updated_at' => $moment])->save();

        return $transaction;
    }

    /**
     * @param array<string, mixed> $query
     */
    protected function report(User $user, string $type, array $query = []): TestResponse
    {
        $url = '/api/reports/'.$type;

        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }

        return $this->actingAs($user, 'sanctum')->getJson($url);
    }
}
