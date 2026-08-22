<?php

namespace Database\Seeders;

use App\Models\LeagueStatus;
use Illuminate\Database\Seeder;

class LeagueStatusSeeder extends Seeder
{
    public function run(): void
    {
        foreach (
            [
                [LeagueStatus::DRAFT, 'Draft', 1],
                [LeagueStatus::SETUP, 'Setup', 2],
                [LeagueStatus::ACTIVE, 'Active', 3],
                [LeagueStatus::COMPLETED, 'Completed', 4],
                [LeagueStatus::ARCHIVED, 'Archived', 5],
            ] as [$key, $label, $sort]
        ) {
            LeagueStatus::query()->updateOrCreate(
                ['key' => $key],
                ['label' => $label, 'sort_order' => $sort],
            );
        }
    }
}
