<?php

namespace App\Filament\Resources\PlayerScores\Pages;

use App\Filament\Resources\PlayerScores\PlayerScoreResource;
use Filament\Resources\Pages\CreateRecord;
use App\Services\PlayerScore\PlayerScoreService;

class CreatePlayerScore extends CreateRecord
{
    protected static string $resource = PlayerScoreResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return app(PlayerScoreService::class)->prepare($data);
    }
}
