<?php

namespace Tests\Feature\Domain;

use App\Models\FantasyTeam;
use App\Models\Formation;
use App\Models\Import;
use App\Models\ImportRowError;
use App\Models\League;
use App\Models\LeagueSetting;
use App\Models\Standing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JsonFieldCastingTest extends TestCase
{
    use RefreshDatabase;

    public function test_json_fields_can_store_and_retrieve_arrays_and_objects(): void
    {
        $this->seedReferenceData();

        $league = League::factory()->create();
        $team = FantasyTeam::factory()->create(['league_id' => $league->id]);

        $setting = LeagueSetting::query()->create([
            'league_id' => $league->id,
            'key' => 'rules',
            'value' => ['lineup' => ['starters' => 11], 'bonuses' => ['clean_sheet']],
        ]);
        $formation = Formation::factory()->create([
            'league_id' => $league->id,
            'fantasy_team_id' => $team->id,
            'snapshot' => ['captain' => ['player' => 'x'], 'bench' => [1, 2]],
        ]);
        $standing = Standing::query()->create([
            'league_id' => $league->id,
            'fantasy_team_id' => $team->id,
            'metadata' => ['tie_breakers' => ['goals_for', 'fantasy_points']],
        ]);
        $import = Import::query()->create([
            'type' => 'players',
            'filename' => 'players.csv',
            'disk' => 'local',
            'path' => 'imports/players.csv',
            'checksum' => hash('sha256', 'test csv contents'),
            'status' => 'completed',
        ]);
        $rowError = ImportRowError::query()->create([
            'import_id' => $import->id,
            'row_number' => 2,
            'row_data' => ['row' => ['external_id' => 'bad'], 'errors' => ['invalid role']],
            'error_message' => 'invalid',
        ]);

        $settingValue = $setting->fresh()->value;
        $formationSnapshot = $formation->fresh()->snapshot;
        $standingMetadata = $standing->fresh()->metadata;
        $rowErrorData = $rowError->fresh()->row_data;

        $this->assertSame(['starters' => 11], $settingValue['lineup']);
        $this->assertSame(['clean_sheet'], $settingValue['bonuses']);
        $this->assertSame(['player' => 'x'], $formationSnapshot['captain']);
        $this->assertSame([1, 2], $formationSnapshot['bench']);
        $this->assertSame(['goals_for', 'fantasy_points'], $standingMetadata['tie_breakers']);
        $this->assertSame(['external_id' => 'bad'], $rowErrorData['row']);
        $this->assertSame(['invalid role'], $rowErrorData['errors']);
    }
}
