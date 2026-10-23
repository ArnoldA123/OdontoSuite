<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\BranchTransferRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BranchTransferRequest>
 */
class BranchTransferRequestFactory extends Factory
{
    protected $model = BranchTransferRequest::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state([
                'role' => 'odontologo',
                'is_active' => true,
            ]),
            'from_branch_id' => Branch::factory(),
            'to_branch_id' => Branch::factory(),
            'status' => BranchTransferRequest::STATUS_PENDING,
            'reason' => 'Traslado solicitado por el profesional.',
            'requested_at' => now(),
            'decided_at' => null,
            'decided_by' => null,
            'decision_notes' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => BranchTransferRequest::STATUS_APPROVED,
            'decided_at' => now(),
        ]);
    }
}
