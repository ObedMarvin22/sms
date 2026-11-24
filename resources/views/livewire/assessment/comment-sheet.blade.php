<div class="flex max-w-4xl flex-col gap-6">
    <x-assessment.sheet-header :stream="$this->classStream()" :subject="$this->subject()" :term="$this->term"
        :can-manage="$this->canManage" active="assessment.comments" comments-only
        :record="$this->sheetRecord" :can-unlock="$this->canUnlock" :problems="$this->problems" />

    @if ($this->rows === [])
        <flux:callout icon="user-group" data-test="no-children">
            <flux:callout.heading>{{ __('No children on this sheet') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Children appear here once they are enrolled in :stream this term.', ['stream' => $this->classStream()->full_name]) }}</flux:callout.text>
        </flux:callout>
    @else
        @if ($this->editable)
            <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-1">
                <flux:text size="sm">{{ __('Write a sentence or two on what each child can do and where they are growing in :area. Each comment saves when you leave the box.', ['area' => $this->subject()->name]) }}</flux:text>
                <flux:text size="sm" class="tabular-nums" aria-live="polite">
                    <span wire:loading.delay wire:target="texts">{{ __('Saving…') }}</span>
                    <span wire:loading.remove wire:target="texts">{{ $errors->isEmpty() ? __('All comments saved') : __('Some comments were not saved') }}</span>
                </flux:text>
            </div>
        @endif

        <ol class="divide-y divide-zinc-200 rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
            @foreach ($this->rows as $row)
                @php($key = 's'.$row->student->id)
                <li class="grid gap-2 px-4 py-3 sm:grid-cols-[12rem_1fr] sm:gap-4 sm:px-5" wire:key="child-{{ $row->student->id }}" data-test="child">
                    <div class="min-w-0 pt-1.5">
                        <label for="comment-{{ $row->student->id }}" @class(['block truncate font-medium', 'text-zinc-900 dark:text-white' => $row->editable, 'text-zinc-400 dark:text-zinc-500' => ! $row->editable])>
                            {{ $row->student->first_name }} {{ $row->student->last_name }}
                        </label>
                        @if ($row->note)
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $row->note }}</div>
                        @endif
                    </div>
                    <div x-data="{ count: @js(mb_strlen($texts[$key] ?? '')) }">
                        <textarea id="comment-{{ $row->student->id }}" rows="2" maxlength="{{ \App\Actions\Assessment\CommentRules::MAX_LENGTH }}"
                            wire:model.live.blur="texts.{{ $key }}" x-on:input="count = $event.target.value.length"
                            @disabled(! $this->editable || ! $row->editable)
                            @error("texts.$key") aria-invalid="true" @enderror
                            placeholder="{{ $this->editable && $row->editable ? __('e.g. Recognises and writes letters a–m; growing in confidence when speaking to the class.') : '' }}"
                            @class([
                                'block w-full resize-y rounded-lg border bg-white px-3 py-2 text-sm text-zinc-900 outline-none transition-colors',
                                'focus:border-zinc-500 focus:ring-2 focus:ring-zinc-500/20 dark:bg-zinc-900 dark:text-white',
                                'disabled:resize-none disabled:border-transparent disabled:bg-zinc-50 disabled:text-zinc-700 dark:disabled:bg-zinc-800/50 dark:disabled:text-zinc-300',
                                'border-zinc-200 dark:border-zinc-700' => ! $errors->has("texts.$key"),
                                'border-red-500 ring-2 ring-red-500/20' => $errors->has("texts.$key"),
                            ])></textarea>
                        <div class="mt-1 flex justify-between gap-3 text-xs">
                            <span class="text-red-600 dark:text-red-400">@error("texts.$key"){{ $message }}@enderror</span>
                            @if ($this->editable && $row->editable)
                                <span class="shrink-0 tabular-nums text-zinc-400" x-text="count + ' / {{ \App\Actions\Assessment\CommentRules::MAX_LENGTH }}'"></span>
                            @endif
                        </div>
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</div>
