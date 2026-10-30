<?php

namespace App\Livewire\Console\Schools;

use App\Actions\Sms\SetSenderId;
use App\Actions\Sms\TopUpCredits;
use App\Models\MessageCreditEntry;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Console: a school's SMS sender ID and credit (top-ups and history).
 */
#[Layout('layouts::console')]
#[Title('SMS')]
class Sms extends Component
{
    #[Locked]
    public int $schoolId;

    public string $senderId = '';

    public string $units = '';

    public string $note = '';

    public function mount(School $school): void
    {
        $this->authorize('create-schools');
        $this->schoolId = $school->id;
        $this->senderId = (string) $school->sms_sender_id;
    }

    public function school(): School
    {
        return School::findOrFail($this->schoolId);
    }

    public function balance(): int
    {
        return app(CurrentSchool::class)->runFor($this->school(), fn (): int => MessageCreditEntry::balance());
    }

    /**
     * @return Collection<int, MessageCreditEntry>
     */
    public function history(): Collection
    {
        return app(CurrentSchool::class)->runFor($this->school(),
            fn () => MessageCreditEntry::with('creator')->latest('id')->limit(30)->get());
    }

    public function saveSender(SetSenderId $setSenderId): void
    {
        $this->authorize('create-schools');
        $this->resetErrorBag();

        try {
            $setSenderId->handle($this->actor(), $this->school(), $this->senderId);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        Flux::toast(variant: 'success', text: __('Sender ID saved.'));
    }

    public function topUp(TopUpCredits $topUpCredits): void
    {
        $this->authorize('create-schools');
        $this->resetErrorBag();

        try {
            $topUpCredits->handle($this->actor(), $this->school(), $this->units, $this->note);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        Flux::toast(variant: 'success', text: __(':units units added.', ['units' => $this->units]));
        $this->units = '';
        $this->note = '';
    }

    public function render(): View
    {
        return view('livewire.console.schools.sms');
    }

    private function actor(): User
    {
        $user = Auth::guard('platform')->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
