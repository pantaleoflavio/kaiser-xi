<?php

namespace App\Filament\Pages;

use App\Enums\CsvImportType;
use App\Enums\ImportStatus;
use App\Models\Import;
use App\Models\User;
use App\Services\Import\CsvImportService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Validation\ValidationException;
use League\Flysystem\FilesystemException;

class ImportData extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.pages.import-data';
    protected static ?string $navigationLabel = 'Import data';
    protected static ?string $title = 'Import data';

    public ?array $data = [];
    public ?array $analysis = null;
    public ?int $importId = null;

    public function mount(): void
    {
        $this->form->fill(['type' => CsvImportType::RealCompetitions->value]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label('Import type')
                    ->options(collect(CsvImportType::cases())->mapWithKeys(
                        fn(CsvImportType $type): array => [$type->value => $type->label()],
                    ))
                    ->live()
                    ->required(),
                FileUpload::make('file')
                    ->label('CSV file')
                    ->disk('local')
                    ->directory('csv-import-uploads')
                    ->visibility('private')
                    ->storeFiles()
                    ->acceptedFileTypes(['text/csv', 'text/plain'])
                    ->rules(['extensions:csv'])
                    ->maxSize(10 * 1024)
                    ->storeFileNamesIn('original_name')
                    ->required(),
            ])
            ->statePath('data');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.competitions');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();
        return $user instanceof User && ($user->isSuperAdmin() || $user->isGlobalAdmin());
    }

    public function updatedDataType(): void
    {
        $this->analysis = null;
        $this->importId = null;
    }

    public function guide(): array
    {
        return app(CsvImportService::class)->contract(CsvImportType::from($this->data['type'] ?? CsvImportType::RealCompetitions->value));
    }

    public function analyse(): void
    {
        try {
            // Dehydrating the form stores Filament's TemporaryUploadedFile on the
            // configured disk before any analysis is performed.
            $data = $this->form->getState();
            $path = $data['file'] ?? null;
            $contents = is_string($path)
                ? Storage::disk('local')->get($path)
                : null;
        } catch (FilesystemException) {
            $this->uploadedFileIsUnavailable();
        }

        if (! is_string($contents)) {
            $this->uploadedFileIsUnavailable();
        }

        $type = CsvImportType::from($data['type']);
        $service = app(CsvImportService::class);

        $this->analysis = $service->analyse($type, $contents);

        $import = $service->createHistory(
            $type,
            $data['original_name'] ?? basename($path),
            $contents,
            (int) Auth::id(),
        );

        $service->storeUnmatchedRows($import, $this->analysis);

        // Do not leave FileUpload pointing at a file that has just been removed.
        $this->data['file'] = null;
        $this->data['original_name'] = null;

        Storage::disk('local')->delete($path);

        if ($this->analysis['has_errors']) {
            $import->update([
                'status' => ImportStatus::Blocked,
                'total_rows' => $this->analysis['counts']['total'],
                'failed_rows' => $this->analysis['counts']['errors'],
            ]);

            foreach ($this->analysis['rows'] as $row) {
                foreach ($row['errors'] as $error) {
                    $import->rowErrors()->create([
                        'row_number' => $row['row_number'],
                        'row_data' => $row['data'],
                        'error_message' => $error,
                    ]);
                }
            }
        }

        $this->importId = $import->id;
    }

    private function uploadedFileIsUnavailable(): never
    {
        $this->analysis = null;
        $this->importId = null;
        $this->data['file'] = null;
        $this->data['original_name'] = null;

        Notification::make()
            ->danger()
            ->title('The uploaded CSV is no longer available')
            ->body('Please select the CSV file again and retry the analysis.')
            ->send();

        throw ValidationException::withMessages([
            'data.file' => 'The uploaded CSV is no longer available. Please upload it again.',
        ]);
    }

    public function confirm(): void
    {
        abort_unless($this->analysis && ! $this->analysis['has_errors'] && $this->importId, 422);
        $queued = app(CsvImportService::class)->queue(Import::findOrFail($this->importId));

        if (! $queued) {
            Notification::make()->warning()->title('Import was already queued or is not ready')->send();

            return;
        }

        $this->importId = null;
        Notification::make()->success()->title('Import queued')->body('Execution will continue in the background.')->send();
    }

    public function downloadTemplate(bool $example = false): StreamedResponse
    {
        $type = CsvImportType::from($this->data['type'] ?? CsvImportType::RealCompetitions->value);
        $csv = app(CsvImportService::class)->template($type, $example);
        return response()->streamDownload(fn() => print($csv), $type->value . ($example ? '-example' : '-template') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
