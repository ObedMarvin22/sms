<?php

namespace App\Livewire\Academic;

use App\Actions\Academic\CreateAcademicYear;
use App\Enums\AcademicPeriodType;
use App\Enums\PermissionKey;
use App\Models\AcademicYear;
use App\Support\Academic\DefaultCalendar;
use App\Support\Settings\SchoolSettings;
use App\Support\Tenancy\CurrentSchool;
use Flux\Flux;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('New academic year')]
class CreateYear extends Component
{
    #[Locked]
    public string $periodType = '';

    public string $name = '';

    public string $starts_on = '';

    public string $ends_on = '';

    /** @var list<array{starts_on: string, ends_on: string, census_on: string}> */
    public array $terms = [];

    public function mount(): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);

        $period = SchoolSettings::for(app(CurrentSchool::class)->require())->academicPeriod();
        $this->periodType = $period->value;

        // Prefill the year after the latest one, or the school year that is running now.
        $latest = AcademicYear::orderByDesc('starts_on')->first();
        $startYear = $latest !== null
            ? $latest->starts_on->year + 1
            : (now()->month >= 8 ? now()->year : now()->year - 1);

        $defaults = DefaultCalendar::for($startYear, $period);
        $this->name = $defaults['name'];
        $this->starts_on = $defaults['starts_on'];
        $this->ends_on = $defaults['ends_on'];
        $this->terms = $defaults['terms'];
    }

    public function save(CreateAcademicYear $createAcademicYear): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);
        $this->resetErrorBag();

        try {
            $year = $createAcademicYear->handle($this->name, $this->starts_on, $this->ends_on, $this->terms);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        Flux::toast(variant: 'success', text: __(':year created.', ['year' => $year->name]));
        $this->redirectRoute('academic-years.index', navigate: true);
    }

    public function periodName(int $sequence): string
    {
        return AcademicPeriodType::from($this->periodType)->periodName($sequence);
    }
}
