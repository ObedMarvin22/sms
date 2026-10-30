<div>
    @if ($this->canView())
        <div class="flex flex-col gap-4" data-test="student-fees">
            <flux:heading size="lg">{{ __('Fees') }}</flux:heading>

            @if ($this->offersBoarding())
                @if ($this->canManage())
                    <flux:radio.group wire:model.live="residence" variant="segmented" :label="__('Residence')" size="sm" class="max-w-60" data-test="residence">
                        @foreach (\App\Enums\Residence::cases() as $option)
                            <flux:radio :value="$option->value" :label="$option->label()" />
                        @endforeach
                    </flux:radio.group>
                @else
                    <flux:text>{{ __('Residence') }}: {{ $this->student()->residence->label() }}</flux:text>
                @endif
                <flux:error name="residence" />
            @endif

            @php($taken = $this->takenIds())
            @if ($this->optionalItems()->isNotEmpty())
                <div>
                    <flux:text size="sm" class="mb-1">{{ __('Optional items (billed every term until removed)') }}</flux:text>
                    @foreach ($this->optionalItems() as $item)
                        <div wire:key="option-{{ $item->id }}">
                            <flux:checkbox :checked="in_array($item->id, $taken, true)" :disabled="! $this->canManage()"
                                wire:click="toggleOption({{ $item->id }})" :label="$item->name" data-test="option-{{ $item->id }}" />
                        </div>
                    @endforeach
                    <flux:error name="options" />
                </div>
            @endif

            @php($itemNames = $this->itemNames())
            @php($given = $this->givenDiscountIds())
            @if ($this->discounts()->isNotEmpty())
                <div>
                    <flux:text size="sm" class="mb-1">{{ __('Discounts') }}</flux:text>
                    @foreach ($this->discounts() as $discount)
                        <div wire:key="discount-{{ $discount->id }}">
                            <flux:checkbox :checked="in_array($discount->id, $given, true)" :disabled="! $this->canManage()"
                                wire:click="toggleDiscount({{ $discount->id }})" data-test="discount-{{ $discount->id }}"
                                :label="$discount->name.' ('.$discount->describe($itemNames).')'" />
                        </div>
                    @endforeach
                    <flux:error name="discounts" />
                </div>
            @endif

            <div>
                <div class="mb-1 flex items-center gap-2">
                    <flux:text size="sm">{{ __('Scholarships') }}</flux:text>
                    @if ($this->canManage())
                        <flux:button size="xs" icon="plus" wire:click="startScholarship" data-test="add-scholarship">{{ __('Add') }}</flux:button>
                    @endif
                </div>
                @forelse ($this->scholarships() as $scholarship)
                    <div class="flex items-center gap-2" wire:key="scholarship-{{ $scholarship->id }}" data-test="scholarship">
                        <span>
                            {{ $scholarship->name }}@if ($scholarship->sponsor) ({{ $scholarship->sponsor }})@endif:
                            {{ $scholarship->describe($itemNames) }}{{ $scholarship->endsTerm ? ', '.__('until :term', ['term' => $scholarship->endsTerm->name]) : '' }}
                        </span>
                        @if ($this->canManage())
                            <flux:button size="xs" variant="ghost" icon="pencil" wire:click="startScholarship({{ $scholarship->id }})" />
                            <flux:button size="xs" variant="ghost" icon="trash" wire:click="removeScholarship({{ $scholarship->id }})"
                                wire:confirm="{{ __('End this scholarship now? Bills already issued keep it.') }}" />
                        @endif
                    </div>
                @empty
                    <flux:text>{{ __('None.') }}</flux:text>
                @endforelse
            </div>

            @if (($term = $this->currentTerm()) && $this->plans()->isNotEmpty())
                <flux:select wire:model.live="planId" :label="__('Instalment plan for :term', ['term' => $term->name])" :disabled="! $this->canManage()" class="max-w-80" data-test="plan">
                    <flux:select.option value="">{{ __('The term\'s default') }}</flux:select.option>
                    @foreach ($this->plans() as $plan)
                        <flux:select.option :value="$plan->id">{{ $plan->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="instalment_plan" />
            @endif
        </div>

        @if ($this->canManage())
            <flux:modal name="scholarship" class="md:w-[30rem]">
                <form wire:submit="saveScholarship" class="flex flex-col gap-4">
                    <flux:heading size="lg">{{ $editingScholarshipId ? __('Edit scholarship') : __('New scholarship') }}</flux:heading>
                    <flux:input wire:model="form.name" :label="__('Name')" :placeholder="__('e.g. Headmaster\'s scholarship')" />
                    <flux:error name="name" />
                    <flux:input wire:model="form.sponsor" :label="__('Sponsor')" :description="__('Optional, e.g. a church or company.')" />
                    <flux:error name="sponsor" />
                    @include('livewire.fees.partials.reduction-fields', ['itemNames' => $itemNames, 'types' => $this->types()])
                    <flux:select wire:model="form.ends_term_id" :label="__('Last term covered')">
                        <flux:select.option value="">{{ __('Until removed') }}</flux:select.option>
                        @foreach ($this->terms() as $option)
                            <flux:select.option :value="$option->id">{{ $option->academicYear->name }} · {{ $option->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="ends_term_id" />
                    <div class="flex justify-end gap-2">
                        <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                        <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                    </div>
                </form>
            </flux:modal>
        @endif
    @endif
</div>
