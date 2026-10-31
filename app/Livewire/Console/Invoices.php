<?php

namespace App\Livewire\Console;

use App\Enums\SaasInvoiceStatus;
use App\Models\SaasInvoice;
use App\Support\Money\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Console: Orange Tree's invoices across all schools, with what is owed and overdue.
 */
#[Layout('layouts::console')]
#[Title('Invoices')]
class Invoices extends Component
{
    use WithPagination;

    #[Url(as: 'status')]
    public string $statusFilter = '';

    public function mount(): void
    {
        $this->authorize('view-billing');
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, SaasInvoice>
     */
    #[Computed]
    public function invoices(): LengthAwarePaginator
    {
        $status = SaasInvoiceStatus::tryFrom($this->statusFilter);

        return SaasInvoice::with(['school', 'term'])->withSum('payments', 'amount')
            ->when($status !== null, fn ($query) => $query->whereStatus($status))
            ->latest('issued_at')->latest('id')
            ->paginate(25);
    }

    /**
     * What schools owe in total, and how much of it is past due.
     *
     * @return array{owed: Money, overdue: Money}
     */
    public function totals(): array
    {
        $owed = 0;
        $overdue = 0;
        foreach (SaasInvoice::live()->withSum('payments', 'amount')->get() as $invoice) {
            $balance = max(0, $invoice->balance()->pesewas);
            $owed += $balance;
            if ($invoice->status() === SaasInvoiceStatus::Overdue) {
                $overdue += $balance;
            }
        }

        return ['owed' => Money::pesewas($owed), 'overdue' => Money::pesewas($overdue)];
    }

    public function render(): View
    {
        return view('livewire.console.invoices');
    }
}
