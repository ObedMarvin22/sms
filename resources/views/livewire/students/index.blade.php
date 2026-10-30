<div class="flex max-w-5xl flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Students') }}</flux:heading>
            <flux:text>{{ __('Admission numbers are generated in your school\'s format unless you type one in.') }}</flux:text>
        </div>
        @if ($this->canManage())
            <div class="flex gap-2">
                <flux:button :href="route('students.import')" icon="arrow-up-tray" wire:navigate>{{ __('Import') }}</flux:button>
                <flux:button wire:click="startCreate" variant="primary" icon="plus" data-test="new-student">{{ __('Admit student') }}</flux:button>
            </div>
        @endif
    </div>

    <flux:input wire:model.live.debounce.300ms="search" :label="__('Search')" icon="magnifying-glass" placeholder="Name or admission number" class="max-w-80" />

    <flux:table :paginate="$this->students">
        <flux:table.columns>
            <flux:table.column>{{ __('Admission no.') }}</flux:table.column>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Class') }}</flux:table.column>
            <flux:table.column>{{ __('Gender') }}</flux:table.column>
            <flux:table.column>{{ __('Admitted') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->students as $student)
                <flux:table.row :key="'student-'.$student->id">
                    <flux:table.cell class="font-mono text-sm" data-test="admission-number">{{ $student->admission_number }}</flux:table.cell>
                    <flux:table.cell data-test="student-name">
                        <flux:link :href="route('students.show', $student)" wire:navigate>{{ $student->full_name }}</flux:link>
                    </flux:table.cell>
                    <flux:table.cell data-test="student-class">{{ $student->currentEnrolment?->stream->full_name ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $student->gender?->label() }}</flux:table.cell>
                    <flux:table.cell>{{ $student->admitted_on->format('j M Y') }}</flux:table.cell>
                    <flux:table.cell><flux:badge size="sm">{{ $student->status->label() }}</flux:badge></flux:table.cell>
                    <flux:table.cell class="text-end">
                        @if ($this->canManage())
                            <flux:button size="sm" variant="ghost" icon="pencil" wire:click="startEdit({{ $student->id }})" />
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7">{{ __('No students found.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @if ($this->canManage())
    <flux:modal name="student" class="md:w-[36rem]">
        <form wire:submit="save" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ $editingId ? __('Edit student') : __('Admit student') }}</flux:heading>

            <div class="grid gap-4 sm:grid-cols-3">
                <flux:input wire:model="form.first_name" :label="__('First name')" />
                <flux:input wire:model="form.other_names" :label="__('Other names')" />
                <flux:input wire:model="form.last_name" :label="__('Last name')" />
            </div>
            <flux:error name="first_name" /><flux:error name="last_name" />

            <div class="grid gap-4 sm:grid-cols-3">
                <flux:select wire:model="form.gender" :label="__('Gender')">
                    <flux:select.option value="">—</flux:select.option>
                    @foreach ($this->genders() as $gender)
                        <flux:select.option :value="$gender->value">{{ $gender->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input type="date" wire:model="form.date_of_birth" :label="__('Date of birth')" />
                <flux:input type="date" wire:model="form.admitted_on" :label="__('Admitted on')" />
            </div>
            <flux:error name="gender" /><flux:error name="date_of_birth" /><flux:error name="admitted_on" />

            @unless ($editingId)
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:select wire:model.live="form.stream_id" :label="__('Class')" data-test="admit-stream">
                        <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                        @foreach ($this->streamOptions as $id => $label)
                            <flux:select.option :value="$id">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @if ($this->selectedStreamIsShs())
                        <flux:select wire:model="form.programme_id" :label="__('Programme')">
                            <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                            @foreach ($this->programmeOptions() as $id => $name)
                                <flux:select.option :value="$id">{{ $name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @endif
                </div>
                <flux:error name="stream_id" /><flux:error name="programme_id" />
            @endunless

            <flux:input wire:model="form.admission_number" :label="__('Admission number')"
                :description="$editingId ? null : __('Leave blank to generate the next one.')" class="max-w-64" />
            <flux:error name="admission_number" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" data-test="save-student">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
    @endif
</div>
