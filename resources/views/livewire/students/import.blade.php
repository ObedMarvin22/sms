<div class="flex max-w-4xl flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Import students') }}</flux:heading>
            <flux:text>{{ __('Fill in the template: one row per student with at least one guardian. The file is checked first; nothing is imported until every row is correct.') }}</flux:text>
        </div>
        <div class="flex gap-2">
            <flux:button :href="route('students.index')" variant="ghost" icon="arrow-left" wire:navigate>{{ __('Students') }}</flux:button>
            <flux:button :href="route('students.import.template')" icon="arrow-down-tray" data-test="template-link">{{ __('Download template') }}</flux:button>
        </div>
    </div>

    @if ($path === null)
        <div>
            <flux:input type="file" wire:model="file" :label="__('Filled-in template (.xlsx)')" accept=".xlsx" />
            <div wire:loading wire:target="file" class="mt-2"><flux:text>{{ __('Checking the file…') }}</flux:text></div>
            <flux:error name="file" />
        </div>
    @else
        <div class="flex flex-col gap-4" data-test="preview">
            <flux:heading size="lg">{{ $fileName }}</flux:heading>

            @if ($problems === [])
                <flux:callout variant="success" icon="check-circle">
                    <flux:callout.heading>{{ __('Ready to import :count students', ['count' => $studentCount]) }}</flux:callout.heading>
                    <flux:callout.text>{{ __(':new new guardians; :linked guardians already on file will be linked.', ['new' => $newGuardians, 'linked' => $linkedGuardians]) }}</flux:callout.text>
                </flux:callout>
                <flux:error name="file" />
                <div class="flex gap-2">
                    <flux:button wire:click="startImport" variant="primary" data-test="start-import">{{ __('Import :count students', ['count' => $studentCount]) }}</flux:button>
                    <flux:button wire:click="cancel" variant="ghost">{{ __('Cancel') }}</flux:button>
                </div>
            @else
                <flux:callout variant="danger" icon="x-circle">
                    <flux:callout.heading>{{ trans_choice('{1} 1 problem to fix|[2,*] :count problems to fix', count($problems), ['count' => count($problems)]) }}</flux:callout.heading>
                    <flux:callout.text>{{ __('Fix these in your file and upload it again. Nothing has been imported.') }}</flux:callout.text>
                </flux:callout>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Row') }}</flux:table.column>
                        <flux:table.column>{{ __('Column') }}</flux:table.column>
                        <flux:table.column>{{ __('Problem') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach (array_slice($problems, 0, 200) as $i => $problem)
                            <flux:table.row :key="'problem-'.$i" data-test="problem-row">
                                <flux:table.cell>{{ $problem['row'] }}</flux:table.cell>
                                <flux:table.cell class="font-mono text-sm">{{ $problem['field'] }}</flux:table.cell>
                                <flux:table.cell class="whitespace-normal">{{ $problem['message'] }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
                @if (count($problems) > 200)
                    <flux:text>{{ __('And :count more.', ['count' => count($problems) - 200]) }}</flux:text>
                @endif
                <div><flux:button wire:click="cancel">{{ __('Upload another file') }}</flux:button></div>
            @endif
        </div>
    @endif

    @if ($import = $this->currentImport())
        @php($counts = $import->counts())
        <div class="flex flex-col gap-3" data-test="import-status"
            @if ($import->status === \App\Enums\StudentImportStatus::Queued) wire:poll.3s @endif>
            <flux:heading size="lg">{{ __('Last import') }}: {{ $import->file_name }}</flux:heading>
            <flux:text>
                {{ $import->status->label() }} ·
                {{ __(':done of :total rows', ['done' => $counts['imported'] + $counts['failed'], 'total' => $import->total_rows]) }} ·
                {{ __(':imported imported, :failed failed', ['imported' => $counts['imported'], 'failed' => $counts['failed']]) }}
                @if ($import->user) · {{ $import->user->name }}, {{ $import->created_at->format('j M Y H:i') }} @endif
            </flux:text>
            <div class="h-2 w-full overflow-hidden rounded bg-zinc-200 dark:bg-zinc-700">
                <div class="h-2 bg-[var(--color-accent)]" style="width: {{ $import->total_rows === 0 ? 100 : (int) round(100 * ($counts['imported'] + $counts['failed']) / $import->total_rows) }}%"></div>
            </div>
            @if ($import->status === \App\Enums\StudentImportStatus::Queued)
                <flux:text size="sm">{{ __('The import runs in the background and this page updates by itself. You can leave it.') }}</flux:text>
            @endif

            @if ($counts['failed'] > 0)
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Row') }}</flux:table.column>
                        <flux:table.column>{{ __('Not imported because') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->failedRows() as $row)
                            <flux:table.row :key="'failed-'.$row->id" data-test="failed-row">
                                <flux:table.cell>{{ $row->row_number }}</flux:table.cell>
                                <flux:table.cell class="whitespace-normal">{{ $row->error }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </div>
    @endif
</div>
