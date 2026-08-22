<?php

namespace Database\Factories;

use App\Models\Import;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Import>
 */
class ImportFactory extends Factory
{
    protected $model = Import::class;

    public function definition(): array
    {
        return [
            'type' => 'players',
            'filename' => 'players.csv',
            'disk' => 'local',
            'path' => 'imports/players.csv',
            'checksum' => hash('sha256', 'players.csv'),
            'status' => 'pending',
            'imported_by_user_id' => User::factory(),
            'total_rows' => 0,
            'successful_rows' => 0,
            'failed_rows' => 0,
            'started_at' => null,
            'completed_at' => null,
        ];
    }
}
