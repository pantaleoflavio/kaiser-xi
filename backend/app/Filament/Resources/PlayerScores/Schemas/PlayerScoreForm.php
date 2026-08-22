<?php

namespace App\Filament\Resources\PlayerScores\Schemas;

use App\Enums\PlayerScoreStatus;
use App\Models\Matchday;
use App\Models\PlayerSeasonRegistration;
use App\Services\PlayerScore\PlayerScoreService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class PlayerScoreForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.player_scores.sections.identity'))
                ->columns(2)
                ->schema([
                    Select::make('player_season_registration_id')
                        ->label(__('admin.labels.player_registration'))
                        ->getSearchResultsUsing(fn(string $search, Get $get): array => self::registrationOptions($search, $get('matchday_id')))
                        ->getOptionLabelUsing(fn($value): ?string => self::registrationOptionLabel($value))
                        ->searchable()
                        ->live()
                        ->disabledOn('edit')
                        ->required(),
                    Select::make('matchday_id')
                        ->label(__('admin.labels.matchday'))
                        ->getSearchResultsUsing(fn(string $search, Get $get): array => self::matchdayOptions($search, $get('player_season_registration_id')))
                        ->getOptionLabelUsing(fn($value): ?string => Matchday::query()->find($value)?->displayLabel())
                        ->searchable()
                        ->live()
                        ->disabledOn('edit')
                        ->required(),
                ]),
            Section::make(__('admin.player_scores.sections.raw_performance'))
                ->columns(3)
                ->schema([
                    TextInput::make('base_rating')->label(__('admin.labels.base_rating'))->numeric()->step(0.01)->rules(['decimal:0,2', 'between:-99.99,99.99'])
                        ->required(fn(Get $get): bool => $get('status') === PlayerScoreStatus::Confirmed->value),
                    Toggle::make('clean_sheet')->label(__('admin.labels.clean_sheet'))->default(false),
                    Toggle::make('is_captain')->label(__('admin.player_scores.real_club_captain'))->helperText(__('admin.player_scores.real_club_captain_help'))->default(false),
                ]),
            Section::make(__('admin.player_scores.sections.match_events'))
                ->columns(3)
                ->schema(array_map(
                    fn(string $field): TextInput => TextInput::make($field)
                        ->label(__('admin.labels.' . $field))
                        ->integer()
                        ->minValue(0)
                        ->default(0)
                        ->required(),
                    PlayerScoreService::EVENT_FIELDS,
                )),
            Section::make(__('admin.player_scores.sections.status_scoring'))
                ->columns(2)
                ->schema([
                    Select::make('status')
                        ->label(__('admin.labels.status'))
                        ->options(PlayerScoreStatus::options())
                        ->required()
                        ->live()
                        ->default(PlayerScoreStatus::Pending->value),
                    TextInput::make('final_score')
                        ->label(__('admin.labels.final_score'))
                        ->numeric()
                        ->step(0.01)
                        ->rules(['decimal:0,2', 'between:-999.99,999.99']),
                ]),
        ]);
    }

    private static function registrationLabel(PlayerSeasonRegistration $registration): string
    {
        $parts = array_filter([
            $registration->player?->display_name,
            trim(implode(' ', array_filter([
                $registration->seasonClub?->season?->realCompetition?->name,
                $registration->seasonClub?->season?->name,
            ]))),
            $registration->seasonClub?->realClub?->name ?? $registration->seasonClub?->display_name,
            $registration->playerRole?->label,
            $registration->external_provider && $registration->external_id
                ? "{$registration->external_provider}: {$registration->external_id}"
                : $registration->external_id,
        ]);

        return implode(' — ', $parts);
    }

    /** @return array<int, string> */
    private static function registrationOptions(string $search, int|string|null $matchdayId): array
    {
        return PlayerSeasonRegistration::query()
            ->with(['player', 'seasonClub.season.realCompetition', 'seasonClub.realClub', 'playerRole'])
            ->when($matchdayId, function (Builder $query, int|string $matchdayId): void {
                $seasonId = Matchday::query()->find($matchdayId)?->season_id;
                $query->whereHas('seasonClub', fn(Builder $query): Builder => $query->where('season_id', $seasonId));
            })
            ->where(function (Builder $query) use ($search): void {
                $query->where('external_id', 'like', "%{$search}%")
                    ->orWhereHas('player', fn(Builder $query): Builder => $query->where('display_name', 'like', "%{$search}%"));
            })
            ->limit(50)
            ->get()
            ->mapWithKeys(fn(PlayerSeasonRegistration $registration): array => [$registration->id => self::registrationLabel($registration)])
            ->all();
    }

    private static function registrationOptionLabel(int|string|null $value): ?string
    {
        $registration = PlayerSeasonRegistration::query()
            ->with(['player', 'seasonClub.season.realCompetition', 'seasonClub.realClub', 'playerRole'])
            ->find($value);

        return $registration ? self::registrationLabel($registration) : null;
    }

    /** @return array<int, string> */
    private static function matchdayOptions(string $search, int|string|null $registrationId): array
    {
        $seasonId = $registrationId
            ? PlayerSeasonRegistration::query()->with('seasonClub:id,season_id')->find($registrationId)?->seasonClub?->season_id
            : null;

        return Matchday::query()
            ->when($seasonId, fn(Builder $query): Builder => $query->where('season_id', $seasonId))
            ->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->when(
                        ctype_digit($search),
                        fn(Builder $query): Builder => $query->orWhere('number', (int) $search),
                    );
            })
            ->limit(50)
            ->get()
            ->mapWithKeys(fn(Matchday $matchday): array => [$matchday->id => $matchday->displayLabel()])
            ->all();
    }
}
