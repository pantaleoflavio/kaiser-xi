<?php

namespace Tests\Feature\Filament;

use App\Enums\CompetitionType;
use App\Enums\RealMatchStatus;
use Illuminate\Support\Arr;
use Tests\TestCase;

class AdminTranslationTest extends TestCase
{
    public function test_admin_translation_files_have_identical_keys(): void
    {
        $translations = collect(['en', 'de', 'it'])->mapWithKeys(fn (string $locale): array => [
            $locale => require lang_path("{$locale}/admin.php"),
        ]);

        foreach ($translations as $translation) {
            $this->assertSame(array_keys(Arr::dot($translations['en'])), array_keys(Arr::dot($translation)));
        }
    }

    public function test_representative_admin_labels_are_translated_in_every_supported_locale(): void
    {
        foreach (['en', 'de', 'it'] as $locale) {
            app()->setLocale($locale);

            foreach (['admin.navigation.groups.competitions', 'admin.resources.real_competitions.singular', 'admin.resources.real_competitions.plural', 'admin.resources.player_season_registrations.singular', 'admin.labels.matchday', 'admin.labels.home_club', 'admin.labels.away_club', 'admin.labels.competition_type'] as $key) {
                $this->assertNotSame($key, __($key));
            }

            $this->assertNotSame('admin.enums.competition_type.domestic_league', CompetitionType::DomesticLeague->label());
            $this->assertNotSame('admin.enums.real_match_status.scheduled', RealMatchStatus::Scheduled->label());
        }
    }
}
