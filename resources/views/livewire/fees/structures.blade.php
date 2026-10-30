<div class="flex max-w-5xl flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Fee structures') }}</flux:heading>
            <flux:text>{{ __('What each class pays per term. Optional items are charged only to students who opt in.') }}</flux:text>
        </div>
        <flux:button :href="route('fees.items')" icon="tag" wire:navigate>{{ __('Fee items') }}</flux:button>
    </div>

    @if ($this->terms->isEmpty())
        <flux:text>{{ __('Add an academic year first.') }}</flux:text>
    @else
        <div class="flex flex-wrap items-end justify-between gap-4">
            <flux:select wire:model.live="termId" :label="__('Term')" class="max-w-72" data-test="structures-term">
                @foreach ($this->terms as $term)
                    <flux:select.option :value="$term->id">{{ $term->academicYear->name }} · {{ $term->name }}</flux:select.option>
                @endforeach
            </flux:select>
            @if ($this->canManage() && ($source = $this->copySource()))
                <flux:button wire:click="copyFromPrevious" icon="document-duplicate" data-test="copy-structures"
                    wire:confirm="{{ __('Copy every structure from :term into this term? Classes that already have one are left alone.', ['term' => $source->name]) }}">
                    {{ __('Copy from :term', ['term' => $source->name]) }}
                </flux:button>
            @endif
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Class') }}</flux:table.column>
                @foreach ($this->residences() as $residence)
                    <flux:table.column>{{ $residence->label() }}</flux:table.column>
                @endforeach
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->classes as $class)
                    <flux:table.row :key="'class-'.$class->id">
                        <flux:table.cell>{{ $class->name }}</flux:table.cell>
                        @foreach ($this->residences() as $residence)
                            @php($structure = $this->structures->get($class->id.'-'.$residence->value))
                            <flux:table.cell data-test="structure-{{ $class->id }}-{{ $residence->value }}">
                                @if ($this->canManage())
                                    <flux:button size="sm" variant="ghost" wire:click="startEdit({{ $class->id }}, '{{ $residence->value }}')">
                                        {{ $structure ? $this->totals($structure)['everyone']->format() : __('Set fees') }}
                                    </flux:button>
                                @else
                                    {{ $structure ? $this->totals($structure)['everyone']->format() : '—' }}
                                @endif
                                @if ($structure && ! $this->totals($structure)['optional']->isZero())
                                    <flux:text size="sm" class="inline">+ {{ $this->totals($structure)['optional']->format() }} {{ __('optional') }}</flux:text>
                                @endif
                            </flux:table.cell>
                        @endforeach
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    @if ($this->canManage())
    <flux:modal name="structure" class="md:w-[32rem]">
        <form wire:submit="save" class="flex flex-col gap-4">
            <div>
                <flux:heading size="lg">{{ $this->editingClass()?->name }} · {{ $editingResidence ? \App\Enums\Residence::from($editingResidence)->label() : '' }}</flux:heading>
                <flux:text>{{ __('Amounts in cedis. Leave an item blank if this class does not pay it.') }}</flux:text>
            </div>
            <flux:error name="residence" />
            @foreach ($this->editItems() as $item)
                <div class="grid grid-cols-[1fr_9rem] items-center gap-3" wire:key="amount-{{ $item->id }}">
                    <div>
                        {{ $item->name }}
                        @if ($item->is_optional)
                            <flux:badge size="sm" color="sky">{{ __('Optional') }}</flux:badge>
                        @endif
                        @unless ($item->is_active)
                            <flux:badge size="sm" color="zinc">{{ __('Archived') }}</flux:badge>
                        @endunless
                    </div>
                    <flux:input wire:model="amounts.{{ $item->id }}" icon="banknotes" placeholder="0.00" inputmode="decimal" data-test="amount-{{ $item->id }}" />
                    <flux:error name="amounts.{{ $item->id }}" class="col-span-2" />
                </div>
            @endforeach
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
    @endif
</div>
