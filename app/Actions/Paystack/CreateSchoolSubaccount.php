<?php

namespace App\Actions\Paystack;

use App\Models\School;
use App\Models\User;
use App\Services\Paystack\PaystackClient;
use App\Services\Paystack\PaystackException;
use Illuminate\Validation\ValidationException;

/**
 * Console (Orange Tree staff): connects a school to Paystack. Checks the account with the bank,
 * creates the school's subaccount (Orange Tree's share 0%) and stores its code. Until this is
 * done the school cannot take online payments. Changing it later is a separate, deliberate job.
 */
class CreateSchoolSubaccount
{
    public function __construct(private PaystackClient $paystack) {}

    public function handle(User $actor, School $school, string $bankCode, string $accountNumber): School
    {
        if ($school->paystack_subaccount_code !== null) {
            throw ValidationException::withMessages(['bank_code' => "{$school->name} is already connected to Paystack."]);
        }

        $accountNumber = preg_replace('/\D/', '', $accountNumber) ?? '';
        if (strlen($accountNumber) < 6 || strlen($accountNumber) > 20) {
            throw ValidationException::withMessages(['account_number' => 'Enter the account or mobile money number (digits only).']);
        }

        try {
            $bank = collect($this->paystack->banks())->firstWhere('code', $bankCode)
                ?? throw ValidationException::withMessages(['bank_code' => 'Choose a bank or mobile money provider from the list.']);
            $accountName = $this->paystack->resolveAccount($accountNumber, $bankCode);
            $code = $this->paystack->createSubaccount($school->name, $bankCode, $accountNumber, $school->email);
        } catch (PaystackException $e) {
            throw ValidationException::withMessages(['account_number' => 'Paystack: '.$e->getMessage()]);
        }

        $school->forceFill([
            'paystack_subaccount_code' => $code,
            'paystack_settlement' => mb_substr("{$bank['name']} · ****".substr($accountNumber, -4).($accountName !== '' ? " · {$accountName}" : ''), 0, 150),
        ])->save();

        activity('schools')->performedOn($school)->causedBy($actor)->event('paystack_connected')
            ->withProperties(['subaccount' => $code, 'settlement' => $school->paystack_settlement])
            ->log('connected to Paystack');

        return $school;
    }
}
