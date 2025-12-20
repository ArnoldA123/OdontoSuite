<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class ReceptionUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'recepcion@easydent.com'],
            [
                'name' => 'Recepción',
                'username' => 'recepcion',
                'password' => Hash::make('recepcion'),
                'role' => 'recepcion',
                'email_verified_at' => now(),
            ]
        );
    }
}
