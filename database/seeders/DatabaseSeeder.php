<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\AiBot;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $aiBot = app(AiBot::class);
        $aiBot->ensureUser();

        $alice = User::query()->updateOrCreate(
            ['email' => 'alice@example.com'],
            [
                'name' => 'Alice',
                'password' => Hash::make('password123'),
            ]
        );

        $bob = User::query()->updateOrCreate(
            ['email' => 'bob@example.com'],
            [
                'name' => 'Bob',
                'password' => Hash::make('password123'),
            ]
        );

        $aiBot->ensureConversation($alice);
        $aiBot->ensureConversation($bob);
    }
}
