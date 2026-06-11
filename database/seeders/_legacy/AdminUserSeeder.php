<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@easydent.com'],
            [
                'name' => 'Administrador',
                'username' => 'admin',
                'password' => Hash::make('admin'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );
    }
}
