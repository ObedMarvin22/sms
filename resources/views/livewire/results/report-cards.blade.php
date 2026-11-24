@use('App\Enums\ReportCardStatus')

<div class="flex max-w-5xl flex-col gap-6" wire:poll.15s>
    <div>
        <flux:heading size="xl">{{ __('Report cards') }}</flux:heading>
        <flux:text>
            {{ __('Make each class\'s report cards once every subject is submitted. They are made in the background, about 15 at a time, and this page updates as they are done.') }}
            @if ($this->term) {{ $this->term->name }}. @endif
        </flux:text>
    </div>

    @if ($this->term === null)
        <flux:callout variant="warning" icon="calendar-days">
            <flux:callout.heading>{{ __('No current term') }}</flux:callout.heading>
        </flux:callout>
    @else
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 text-left text-xs text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                        <th class="px-4 py-2 font-medium">{{ __('Class') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('Subjects submitted') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('Report cards') }}</th>
                        <th class="px-4 py-2"><span class="sr-only">{{ __('Actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($this->classes as $row)
                        @php($ready = $row['subjects'] > 0 && $row['submitted'] >= $row['subjects'])
                        @php($made = $row['statuses'][ReportCardStatus::Generated->value] + $row['statuses'][ReportCardStatus::Approved->value] + $row['statuses'][ReportCardStatus::Published->value])
                        <tr wire:key="class-{{ $row['stream']->id }}" data-test="class-row">
                            <td class="px-4 py-2.5 font-medium text-zinc-900 dark:text-white">{{ $row['stream']->full_name }}</td>
                            <td class="px-4 py-2.5 tabular-nums">
                                <span @class(['text-green-700 dark:text-green-400' => $ready, 'text-zinc-600 dark:text-zinc-300' => ! $ready])>
                                    {{ $row['submitted'] }} / {{ $row['subjects'] }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5">
                                @if ($row['cards'] === 0)
                                    <span class="text-zinc-400">{{ __('None yet') }}</span>
                                @else
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        @if ($row['statuses'][ReportCardStatus::Published->value])
                                            <flux:badge size="sm" color="green">{{ __(':n published', ['n' => $row['statuses'][ReportCardStatus::Published->value]]) }}</flux:badge>
                                        @endif
                                        @if ($row['statuses'][ReportCardStatus::Approved->value])
                                            <flux:badge size="sm" color="sky">{{ __(':n approved', ['n' => $row['statuses'][ReportCardStatus::Approved->value]]) }}</flux:badge>
                                        @endif
                                        @if ($row['statuses'][ReportCardStatus::Generated->value])
                                            <flux:badge size="sm" color="zinc">{{ __(':n made', ['n' => $row['statuses'][ReportCardStatus::Generated->value]]) }}</flux:badge>
                                        @endif
                                        @if ($row['statuses'][ReportCardStatus::Draft->value])
                                            <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ __(':n of :total to make', ['n' => $row['statuses'][ReportCardStatus::Draft->value], 'total' => $row['cards']]) }}</span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            @php($generated = $row['statuses'][ReportCardStatus::Generated->value])
                            @php($approved = $row['statuses'][ReportCardStatus::Approved->value])
                            @php($published = $row['statuses'][ReportCardStatus::Published->value])
                            <td class="px-4 py-2.5">
                                <div class="flex flex-wrap justify-end gap-1.5">
                                    @if ($published + $approved < $row['cards'] || $row['cards'] === 0)
                                        <flux:button size="sm" :variant="$ready && $made === 0 ? 'primary' : 'ghost'" icon="document-text"
                                            wire:click="generate({{ $row['stream']->id }})" :disabled="! $ready"
                                            data-test="generate-{{ $row['stream']->id }}">
                                            {{ $made > 0 ? __('Make again') : __('Make report cards') }}
                                        </flux:button>
                                    @endif
                                    @if ($generated > 0 && $row['statuses'][ReportCardStatus::Draft->value] === 0)
                                        <flux:button size="sm" variant="primary" icon="check" wire:click="approve({{ $row['stream']->id }})"
                                            wire:confirm="{{ __('Approve :class\'s report cards? Check a few on the Results page first.', ['class' => $row['stream']->full_name]) }}"
                                            data-test="approve-{{ $row['stream']->id }}">{{ __('Approve') }}</flux:button>
                                    @endif
                                    @if ($approved > 0)
                                        <flux:button size="sm" variant="primary" icon="paper-airplane" wire:click="publish({{ $row['stream']->id }})"
                                            wire:confirm="{{ __('Publish :class\'s report cards to parents? They are told in the parent app.', ['class' => $row['stream']->full_name]) }}"
                                            data-test="publish-{{ $row['stream']->id }}">{{ __('Publish') }}</flux:button>
                                    @endif
                                    @if ($approved + $published > 0)
                                        <flux:button size="sm" variant="ghost" icon="arrow-uturn-left" wire:click="withdraw({{ $row['stream']->id }})"
                                            wire:confirm="{{ __('Withdraw :class\'s report cards? Parents stop seeing them until you publish again.', ['class' => $row['stream']->full_name]) }}"
                                            data-test="withdraw-{{ $row['stream']->id }}">{{ __('Withdraw') }}</flux:button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <flux:text size="sm">{{ __('Make, check, approve, then publish. Parents see report cards only once published, and get a notice in the parent app. "Make again" remakes cards that are not approved yet; "Withdraw" takes approved or published cards back for corrections. Open Results and click a name to look at one.') }}</flux:text>
    @endif
</div>
