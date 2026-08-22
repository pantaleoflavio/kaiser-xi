<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section heading="Select CSV file">
            <form wire:submit="analyse" class="space-y-4">
                {{ $this->form }}
                <x-filament::button type="submit">Analyse</x-filament::button>
            </form>
        </x-filament::section>

        @php($guide = $this->guide())
        <x-filament::section heading="CSV format / Import guide">
            <dl class="grid gap-2 md:grid-cols-2">
                <div>
                    <dt class="font-semibold">Record identifier</dt>
                    <dd>{{ $guide['identifier'] }}</dd>
                </div>
                <div>
                    <dt class="font-semibold">Required on create</dt>
                    <dd>{{ implode(', ', $guide['required_create']) }}</dd>
                </div>
                <div>
                    <dt class="font-semibold">Optional columns</dt>
                    <dd>{{ implode(', ', $guide['optional']) }}</dd>
                </div>
                <div>
                    <dt class="font-semibold">Dependency position</dt>
                    <dd>{{ $guide['dependency'] }}</dd>
                </div>
            </dl>
            <p class="mt-3"><strong>Formats:</strong> {{ implode('; ', $guide['formats']) }}</p>
            <p><strong>Create/update and empty cells:</strong> {{ $guide['behavior'] }} Empty cells clear nullable
                supplied fields; omitted columns preserve existing values.</p>
            <p><strong>Header:</strong> <code>{{ implode(',', $guide['columns']) }}</code></p>
            <p><strong>Example:</strong> <code>{{ implode(',', $guide['example']) }}</code></p>
            <p><strong>Caveats:</strong> {{ implode(' ', $guide['caveats']) }}</p>
            <p class="mt-2 text-sm">UTF-8 CSV, comma delimiter, lowercase snake_case header, RFC-4180 quoting,
                true/false booleans, and YYYY-MM-DD dates. Unknown or duplicate columns are rejected.</p>
            <div class="mt-4 flex gap-2"><x-filament::button wire:click="downloadTemplate">Download CSV
                    template</x-filament::button><x-filament::button color="gray"
                    wire:click="downloadTemplate(true)">Download example CSV</x-filament::button></div>
        </x-filament::section>

        @if ($analysis)
            <x-filament::section heading="Analysis preview">
                <div class="grid grid-cols-2 gap-3 md:grid-cols-6">
                    @foreach (['total', 'create', 'update', 'unchanged', 'warnings', 'errors'] as $count)
                        <div class="rounded-lg bg-gray-100 p-3 dark:bg-gray-800">
                            <strong>{{ ucfirst($count) }}</strong><br>{{ $analysis['counts'][$count] }}</div>
                    @endforeach
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr>
                                <th>CSV row</th>
                                <th>Identifier</th>
                                <th>Action</th>
                                <th>Changed fields</th>
                                <th>Warnings / errors</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($analysis['rows'] as $row)
                                <tr class="border-t">
                                    <td>{{ $row['row_number'] }}</td>
                                    <td>{{ $row['identifier'] }}</td>
                                    <td>{{ $row['action'] }}</td>
                                    <td>{{ implode(', ', $row['changes']) }}</td>
                                    <td>{{ implode('; ', array_merge($row['warnings'], $row['errors'])) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4"><x-filament::button wire:click="confirm" :disabled="$analysis['has_errors'] || $importId === null">Confirm
                        Import</x-filament::button></div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
