<div class="flex max-w-5xl flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Announcements') }}</flux:heading>
            <flux:text>{{ __('One-way notices to parents in the parent app. They cannot be edited once sent: withdraw and send again to correct one.') }}</flux:text>
        </div>
        @if ($this->streams->isNotEmpty())
            <flux:button wire:click="startCreate" variant="primary" icon="megaphone" data-test="new-announcement">{{ __('New announcement') }}</flux:button>
        @endif
    </div>

    @if ($this->streams->isEmpty())
        <flux:callout icon="information-circle">
            <flux:callout.text>{{ __('You can send announcements to the classes you are class teacher of. You have none this year.') }}</flux:callout.text>
        </flux:callout>
    @endif

    @php($seenCounts = $this->seenFor($this->announcements))
    <flux:table :paginate="$this->announcements">
        <flux:table.columns>
            <flux:table.column>{{ __('Sent') }}</flux:table.column>
            <flux:table.column>{{ __('Announcement') }}</flux:table.column>
            <flux:table.column>{{ __('To') }}</flux:table.column>
            <flux:table.column>{{ __('Seen by') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->announcements as $announcement)
                @php($seen = $seenCounts[$announcement->id] ?? null)
                <flux:table.row :key="'announcement-'.$announcement->id" data-test="announcement-row">
                    <flux:table.cell class="whitespace-nowrap align-top">
                        {{ $announcement->sent_at->format('j M Y H:i') }}
                        <flux:text size="sm">{{ $announcement->sender?->name }}</flux:text>
                    </flux:table.cell>
                    <flux:table.cell class="whitespace-normal align-top">
                        <div class="font-medium">{{ $announcement->title }}</div>
                        <flux:text size="sm" class="line-clamp-2">{{ $announcement->body }}</flux:text>
                        @if ($announcement->withdrawn_at)
                            <flux:badge size="sm" color="zinc">{{ __('Withdrawn :date', ['date' => $announcement->withdrawn_at->format('j M')]) }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="whitespace-normal align-top">
                        {{ $announcement->audience === \App\Enums\AnnouncementAudience::School
                            ? __('Whole school')
                            : $announcement->streams->map(fn ($stream) => $stream->full_name)->join(', ') }}
                    </flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap align-top" data-test="seen">
                        @if ($announcement->withdrawn_at)
                            —
                        @elseif ($seen)
                            {{ __(':seen of :total families', $seen) }}
                        @else
                            {{ __('Sending…') }}
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="text-end align-top">
                        @if ($this->canWithdraw($announcement))
                            <flux:button size="sm" variant="ghost" wire:click="withdraw({{ $announcement->id }})"
                                         wire:confirm="{{ __('Withdraw this announcement? Parents will no longer see it.') }}">
                                {{ __('Withdraw') }}
                            </flux:button>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">{{ __('No announcements yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @if ($this->streams->isNotEmpty())
    <flux:modal name="announcement" class="md:w-[34rem]">
        <form wire:submit="send" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('New announcement') }}</flux:heading>
            <flux:input wire:model="form.title" :label="__('Title')" maxlength="120" :placeholder="__('e.g. PTA meeting on Saturday')" />
            <flux:error name="title" />
            <flux:textarea wire:model="form.body" :label="__('Message')" rows="6" maxlength="2000" />
            <flux:error name="body" />

            @if ($this->canSendToSchool())
                <flux:radio.group wire:model.live="form.audience" :label="__('Send to')">
                    <flux:radio value="school" :label="__('Whole school')" />
                    <flux:radio value="streams" :label="__('Chosen classes')" />
                </flux:radio.group>
            @endif
            <flux:error name="audience" />

            @if ($form['audience'] === 'streams')
                <flux:checkbox.group wire:model="form.stream_ids" :label="__('Classes')" class="grid max-h-60 grid-cols-2 gap-1 overflow-y-auto">
                    @foreach ($this->streams as $stream)
                        <flux:checkbox :value="(string) $stream->id" :label="$stream->full_name" />
                    @endforeach
                </flux:checkbox.group>
                <flux:error name="stream_ids" />
                <flux:error name="stream_ids.*" />
            @endif

            <flux:text size="sm">{{ __('Parents get a notice in the app. No SMS is sent.') }}</flux:text>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Send now') }}</flux:button>
            </div>
        </form>
    </flux:modal>
    @endif
</div>
