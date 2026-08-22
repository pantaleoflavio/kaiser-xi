<?php

namespace App\Filament\Resources\PlayerScores\Pages;

use App\Enums\PlayerScoreStatus;
use App\Filament\Resources\PlayerScores\PlayerScoreResource;
use App\Models\PlayerScore;
use App\Services\PlayerScore\PlayerScoreService;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPlayerScore extends EditRecord
{
    protected static string $resource = PlayerScoreResource::class;


    public function mount(int|string $record): void
    {
        parent::mount($record);

        if ($this->getRecord()->status === PlayerScoreStatus::Confirmed) {
            Notification::make()
                ->warning()
                ->title(__('admin.player_scores.confirmed_warning_title'))
                ->body(__('admin.player_scores.confirmed_warning_body'))
                ->persistent()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->requiresConfirmation()
                ->modalDescription(__('admin.player_scores.delete_warning')),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var PlayerScore $record */
        $record = $this->getRecord();

        return app(PlayerScoreService::class)->prepare($data, $record);
    }
}
