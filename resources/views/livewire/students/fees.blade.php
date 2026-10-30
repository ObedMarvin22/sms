<div>
    @if ($this->canView())
        <div class="flex flex-col gap-3" data-test="student-fees">
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
            @if ($this->optionalItems()->isEmpty())
                <flux:text>{{ __('The school has no optional fee items.') }}</flux:text>
            @else
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
        </div>
    @endif
</div>
