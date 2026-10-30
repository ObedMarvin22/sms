<?php

namespace App\Livewire\Console\Schools;

use App\Actions\Paystack\CreateSchoolSubaccount;
use App\Models\School;
use App\Models\User;
use App\Services\Paystack\PaystackClient;
use App\Services\Paystack\PaystackException;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Console: connects a school to Paystack so parents can pay online. Money settles to the
 * school's own account; Orange Tree's share is 0%.
 */
#[Layout('layouts::console')]
#[Title('Online payments')]
class Payments extends Component
{
    #[Locked]
    public int $schoolId;

    public string $bankCode = '';

    public string $accountNumber = '';

    public function mount(School $school): void
    {
        $this->authorize('create-schools');
        $this->schoolId = $school->id;
    }

    public function school(): School
    {
        return School::findOrFail($this->schoolId);
    }

    /**
     * @return list<array{name: string, code: string, type: string}>
     */
    public function banks(): array
    {
        try {
            return app(PaystackClient::class)->banks();
        } catch (PaystackException $e) {
            $this->addError('bank_code', $e->getMessage());

            return [];
        }
    }

    public function connect(CreateSchoolSubaccount $createSubaccount): void
    {
        $this->authorize('create-schools');
        $this->resetErrorBag();
        $user = Auth::guard('platform')->user();
        abort_unless($user instanceof User, 403);

        try {
            $school = $createSubaccount->handle($user, $this->school(), $this->bankCode, $this->accountNumber);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        Flux::toast(variant: 'success', text: __(':name can now take online payments.', ['name' => $school->name]));
    }

    public function render(): View
    {
        return view('livewire.console.schools.payments');
    }
}
