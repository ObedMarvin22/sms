<?php

namespace App\Livewire\Fees;

use App\Enums\CreditEntryType;
use App\Enums\PermissionKey;
use App\Enums\SmsStatus;
use App\Models\MessageCreditEntry;
use App\Models\SmsMessage;
use App\Models\Term;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * SMS credit and the messages sent (finance staff).
 */
#[Title('Messages')]
class Messages extends Component
{
    use WithPagination;

    #[Url(as: 'status')]
    public string $statusFilter = '';

    public function mount(): void
    {
        $this->authorize(PermissionKey::FinanceView->value);
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function balance(): int
    {
        return MessageCreditEntry::balance();
    }

    /**
     * Units used since the current term started (messages minus refunds).
     */
    public function usedThisTerm(): int
    {
        $start = Term::current()->value('starts_on');
        if ($start === null) {
            return 0;
        }

        return -(int) MessageCreditEntry::whereIn('type', [CreditEntryType::Message->value, CreditEntryType::Refund->value])
            ->where('created_at', '>=', $start)->sum('units');
    }

    /**
     * @return LengthAwarePaginator<int, SmsMessage>
     */
    #[Computed]
    public function messages(): LengthAwarePaginator
    {
        $status = SmsStatus::tryFrom($this->statusFilter);

        return SmsMessage::query()->when($status !== null, fn ($q) => $q->where('status', $status->value))
            ->latest('id')->paginate(25);
    }

    public function render(): View
    {
        return view('livewire.fees.messages');
    }
}
