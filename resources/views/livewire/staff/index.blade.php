<div class="flex max-w-5xl flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Staff') }}</flux:heading>
            <flux:text>{{ __('Staff with an email get a login and an invitation. Others are records only.') }}</flux:text>
        </div>
        <flux:button wire:click="startCreate" variant="primary" icon="plus" data-test="new-staff">{{ __('Add staff') }}</flux:button>
    </div>

    <div class="flex flex-wrap items-end gap-4">
        <flux:input wire:model.live.debounce.300ms="search" :label="__('Search')" icon="magnifying-glass" placeholder="Name, email or staff number" class="max-w-80" />
        <flux:checkbox wire:model.live="showLeft" :label="__('Include staff who have left')" />
    </div>

    <flux:table :paginate="$this->staff">
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Job title') }}</flux:table.column>
            <flux:table.column>{{ __('Roles') }}</flux:table.column>
            <flux:table.column>{{ __('Login') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->staff as $member)
                <flux:table.row :key="'staff-'.$member->id">
                    <flux:table.cell>
                        <div class="font-medium" data-test="staff-name">{{ $member->full_name }}</div>
                        <flux:text size="sm">{{ $member->email }} {{ $member->staff_number ? '· '.$member->staff_number : '' }}</flux:text>
                    </flux:table.cell>
                    <flux:table.cell>{{ $member->job_title }}</flux:table.cell>
                    <flux:table.cell>
                        @foreach ($member->user?->getRoleNames() ?? [] as $roleName)
                            <flux:badge size="sm">{{ \App\Enums\RoleKey::tryFrom($roleName)?->label() ?? $roleName }}</flux:badge>
                        @endforeach
                    </flux:table.cell>
                    <flux:table.cell>
                        @if (! $member->isActive())
                            <flux:badge size="sm" color="zinc">{{ __('Left') }}</flux:badge>
                        @elseif ($member->user === null)
                            <flux:text size="sm">{{ __('No login') }}</flux:text>
                        @elseif ($member->user->email_verified_at === null)
                            <flux:badge size="sm" color="amber">{{ __('Invited') }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="green">{{ __('Active') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="text-end">
                        @if ($member->isActive())
                            <flux:button size="sm" variant="ghost" icon="pencil" wire:click="startEdit({{ $member->id }})">{{ __('Edit') }}</flux:button>
                            @if ($member->user && $member->user->email_verified_at === null)
                                <flux:button size="sm" variant="ghost" wire:click="resendInvitation({{ $member->id }})">{{ __('Resend invite') }}</flux:button>
                            @endif
                            <flux:button size="sm" variant="ghost" wire:click="markLeft({{ $member->id }})"
                                wire:confirm="{{ __(':name has left? Their login will be switched off.', ['name' => $member->full_name]) }}">{{ __('Has left') }}</flux:button>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">{{ __('No staff found.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="staff" class="md:w-[40rem]">
        <form wire:submit="save" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ $editingId ? __('Edit staff') : __('Add staff') }}</flux:heading>

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
                <flux:input wire:model="form.staff_number" :label="__('Staff number')" />
                <flux:input type="date" wire:model="form.employed_on" :label="__('Employed on')" />
            </div>
            <flux:error name="staff_number" /><flux:error name="employed_on" />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="form.email" type="email" :label="__('Email')" :description="__('Needed for a login.')" />
                <flux:input wire:model="form.phone" type="tel" :label="__('Phone')" placeholder="024 412 3456" />
            </div>
            <flux:error name="email" /><flux:error name="phone" />

            <flux:input wire:model="form.job_title" :label="__('Job title')" />

            @if ($this->canManageRoles())
                <flux:checkbox.group wire:model="roles" :label="__('Roles')" class="grid gap-2 sm:grid-cols-2">
                    @foreach ($this->grantableRoles() as $role)
                        <flux:checkbox :value="$role->value" :label="$role->label()" />
                    @endforeach
                </flux:checkbox.group>
                <flux:error name="roles" />
            @endif

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" data-test="save-staff">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
