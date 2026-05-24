<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@emc.com'],
            [
                'name'     => 'Admin EMC',
                'password' => Hash::make('EMC@admin2024'),
            ]
        );
    }
}
