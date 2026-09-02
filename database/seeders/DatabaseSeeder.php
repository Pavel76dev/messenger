<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'alice@example.com'],
            [
                'name' => 'Alice',
                'password' => Hash::make('password123'),
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'bob@example.com'],
            [
                'name' => 'Bob',
                'password' => Hash::make('password123'),
            ]
        );
    }
}
