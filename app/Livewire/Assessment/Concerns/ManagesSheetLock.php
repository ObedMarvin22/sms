<?php

namespace App\Livewire\Assessment\Concerns;

use App\Actions\Assessment\SheetLock;
use App\Actions\Assessment\SubmitSheet;
use App\Actions\Assessment\UnlockSheet;
use App\Enums\PermissionKey;
use App\Models\AssessmentSheet;
use App\Support\Assessment\ScoreSheet;
use Flux\Flux;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;

/**
 * Submit and unlock for a class-and-subject page (Tasks and Marks). The host provides term,
 * classStream(), subject(), canManage and scoreSheet().
 */
trait ManagesSheetLock
{
    public string $unlockReason = '';

    abstract protected function scoreSheet(): ScoreSheet;

    #[Computed]
    public function sheetRecord(): ?AssessmentSheet
    {
        return SheetLock::find($this->term->id, $this->streamId, $this->subjectId)?->load(['submitter', 'unlocker']);
    }

    #[Computed]
    public function locked(): bool
    {
        return $this->sheetRecord?->isLocked() ?? false;
    }

    /**
     * May change tasks and marks right now: teaches the subject (or is a head) and not locked.
     */
    #[Computed]
    public function editable(): bool
    {
        return $this->canManage && ! $this->locked;
    }

    #[Computed]
    public function canUnlock(): bool
    {
        return $this->locked && (auth()->user()?->can(PermissionKey::GradesApprove->value) ?? false);
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function problems(): array
    {
        return $this->locked ? [] : SheetLock::problems($this->scoreSheet());
    }

    public function submitSheet(SubmitSheet $submit): void
    {
        try {
            $submit->handle(auth()->user(), $this->term, $this->classStream(), $this->subject());
        } catch (ValidationException $e) {
            unset($this->problems);
            Flux::toast(variant: 'danger', text: __('Not ready to submit yet: see what is missing above the sheet.'));

            return;
        }

        $this->forgetSheetState();
        Flux::toast(variant: 'success', text: __('Marks submitted and locked.'));
    }

    public function unlockSheet(UnlockSheet $unlock): void
    {
        $this->resetErrorBag('unlock_reason');
        $record = $this->sheetRecord ?? abort(404);

        try {
            $unlock->handle(auth()->user(), $record, $this->unlockReason);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $this->unlockReason = '';
        $this->forgetSheetState();
        Flux::modal('unlock-sheet')->close();
        Flux::toast(variant: 'success', text: __('Unlocked. The subject teacher can correct the marks and submit again.'));
    }

    private function forgetSheetState(): void
    {
        unset($this->sheetRecord, $this->locked, $this->editable, $this->canUnlock, $this->problems);
    }
}
