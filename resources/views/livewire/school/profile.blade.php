<div class="flex max-w-2xl flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('School profile') }}</flux:heading>
        <flux:text>{{ __('How your school appears to staff and parents.') }}</flux:text>
    </div>

    <form wire:submit="save" class="flex flex-col gap-6">
        <flux:input wire:model="name" :label="__('School name')" required />

        <flux:input :value="$subdomain.'.'.config('app.base_domain')" :label="__('Web address')" disabled
            :description="__('Contact Orange Tree to change it.')" />

        <div class="flex flex-col gap-3">
            <flux:label>{{ __('Logo') }}</flux:label>
            <div class="flex items-center gap-4">
                @if ($logo)
                    <img src="{{ $logo->temporaryUrl() }}" alt="" class="size-16 rounded-md object-contain" data-test="logo-preview">
                @elseif ($this->school()->logo_path)
                    <img src="{{ route('school.logo') }}?v={{ $this->school()->updated_at?->timestamp }}" alt="{{ __('Current logo') }}" class="size-16 rounded-md object-contain" data-test="logo-current">
                @endif
                <input type="file" wire:model="logo" accept="image/png,image/jpeg,image/webp" class="text-sm" data-test="logo-input">
            </div>
            <flux:text size="sm">{{ __('PNG, JPG or WebP, up to 1 MB.') }}</flux:text>
            <flux:error name="logo" />
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <flux:input wire:model="primary_colour" :label="__('Primary colour')" placeholder="#0A7D3C" />
            <flux:input wire:model="secondary_colour" :label="__('Secondary colour')" placeholder="#F5D90A" />
        </div>

        <flux:separator />

        <div class="grid gap-6 sm:grid-cols-2">
            <flux:input wire:model="phone" :label="__('Phone')" type="tel" placeholder="024 412 3456" />
            <flux:input wire:model="email" :label="__('Email')" type="email" />
        </div>
        <flux:textarea wire:model="address" :label="__('Address')" rows="2" />

        <flux:checkbox.group wire:model="levels" :label="__('Levels offered')">
            @foreach ($this->levelOptions() as $level)
                <flux:checkbox :value="$level->value" :label="$level->label()" />
            @endforeach
        </flux:checkbox.group>

        <div>
            <flux:button type="submit" variant="primary" data-test="save-school-profile">{{ __('Save') }}</flux:button>
        </div>
    </form>

    <flux:separator />

    <form wire:submit="saveAdmissionFormat" class="flex flex-col gap-4" data-test="admission-format">
        <div>
            <flux:heading size="lg">{{ __('Admission numbers') }}</flux:heading>
            <flux:text>{{ __('Tokens: {PREFIX}, {YYYY}, {YY} and {SEQ:n}. With the year in it, numbering restarts each year. Existing numbers do not change.') }}</flux:text>
        </div>
        <div class="grid gap-4 sm:grid-cols-[10rem_1fr]">
            <flux:input wire:model.live.debounce.300ms="admission_prefix" :label="__('Prefix')" />
            <flux:input wire:model.live.debounce.300ms="admission_number_format" :label="__('Format')" />
        </div>
        <flux:error name="admission_prefix" /><flux:error name="admission_number_format" />
        <flux:text data-test="admission-preview">{{ __('Next number looks like:') }} <span class="font-mono">{{ $this->admissionPreview() ?? '—' }}</span></flux:text>
        <div>
            <flux:button type="submit">{{ __('Save format') }}</flux:button>
        </div>
    </form>

    <flux:separator />

    <form wire:submit="saveAttendanceLockTime" class="flex flex-col gap-4" data-test="attendance-lock">
        <div>
            <flux:heading size="lg">{{ __('Attendance') }}</flux:heading>
            <flux:text>{{ __('Each day\'s register locks at this time. After it, only staff who may edit locked registers can change marks, with a reason.') }}</flux:text>
        </div>
        <flux:input type="time" wire:model="attendance_lock_time" :label="__('Register locks at')" class="max-w-40" />
        <flux:error name="attendance_lock_time" />
        <div>
            <flux:button type="submit">{{ __('Save lock time') }}</flux:button>
        </div>
    </form>

    <flux:separator />

    <div class="flex flex-col gap-2" data-test="online-payments">
        <flux:heading size="lg">{{ __('Online payments') }}</flux:heading>
        @if ($this->school()->paystack_subaccount_code)
            <flux:text>{{ __('Parents can pay online. Money settles to: :account', ['account' => $this->school()->paystack_settlement]) }}</flux:text>
            <flux:radio.group wire:model.live="paystack_charge_payer" :label="__('Paystack\'s charge (about :rate)', ['rate' => \App\Support\Money\Percent::format(\App\Actions\Paystack\PaystackCharge::basisPoints())])">
                <flux:radio value="school" :label="__('The school absorbs it')" :description="__('Parents pay exactly the fee amount.')" />
                <flux:radio value="parent" :label="__('Parents pay it')" :description="__('The charge is added at checkout so the school receives the full fee.')" />
            </flux:radio.group>
            <flux:error name="paystack_charge_payer" />
        @else
            <flux:text>{{ __('Not set up yet. Orange Tree connects your school to Paystack with your bank or mobile money account.') }}</flux:text>
        @endif
    </div>

    <flux:separator />

    <div class="flex flex-col gap-2" data-test="offers-boarding">
        <flux:heading size="lg">{{ __('Boarding') }}</flux:heading>
        <flux:switch wire:model.live="offers_boarding" :label="__('This school takes boarders')"
            :description="__('Adds boarding fee structures and a day / boarding choice on each student.')" />
        <flux:error name="offers_boarding" />
    </div>
</div>
