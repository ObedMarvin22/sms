<?php

namespace App\Livewire\Console\Schools;

use App\Models\School;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::console')]
#[Title('Schools')]
class Index extends Component
{
    use WithPagination;

    /**
     * @return LengthAwarePaginator<int, School>
     */
    #[Computed]
    public function schools(): LengthAwarePaginator
    {
        return School::query()->latest()->paginate(25);
    }

    public function render(): View
    {
        return view('livewire.console.schools.index');
    }
}
