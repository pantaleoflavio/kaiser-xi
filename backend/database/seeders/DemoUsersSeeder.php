<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUsersSeeder extends Seeder
{
    public const USERS = [
        'demo.commissioner@example.com' => 'Demo Commissioner',
        'demo.cocommissioner@example.com' => 'Demo Co-Commissioner',
        'demo.participant1@example.com' => 'Demo Participant One',
        'demo.participant2@example.com' => 'Demo Participant Two',
        'demo.nonmember@example.com' => 'Demo Non-Member',
    ];

    public function run(): void
    {
        foreach (self::USERS as $email => $name) {
            User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('password'),
                ],
            );
        }
    }
}
