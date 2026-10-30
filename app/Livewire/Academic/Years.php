<?php

namespace App\Livewire\Academic;

use App\Actions\Academic\DeleteAcademicYear;
use App\Actions\Academic\SetAcademicPeriodType;
use App\Actions\Academic\SetCurrentTerm;
use App\Actions\Academic\UpdateTermDates;
use App\Enums\AcademicPeriodType;
use App\Enums\PermissionKey;
use App\Models\AcademicYear;
use App\Models\Term;
use App\Support\Settings\SchoolSettings;
use App\Support\Tenancy\CurrentSchool;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Academic years')]
class Years extends Component
{
    public string $academicPeriod = '';

    #[Locked]
    public ?int $editingTermId = null;

    public string $editStartsOn = '';

    public string $editEndsOn = '';

    public string $editCensusOn = '';

    public function mount(): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);
        $this->academicPeriod = SchoolSettings::for(app(CurrentSchool::class)->require())->academicPeriod()->value;
    }

    /**
     * @return Collection<int, AcademicYear>
     */
    #[Computed]
    public function years(): Collection
    {
        return AcademicYear::with('terms')->orderByDesc('starts_on')->get();
    }

    public function updatedAcademicPeriod(SetAcademicPeriodType $setAcademicPeriodType): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);

        try {
            $setAcademicPeriodType->handle(app(CurrentSchool::class)->require(), AcademicPeriodType::from($this->academicPeriod));
            Flux::toast(variant: 'success', text: __('Saved.'));
        } catch (ValidationException $e) {
            $this->academicPeriod = SchoolSettings::for(app(CurrentSchool::class)->require()->refresh())->academicPeriod()->value;
            $this->addError('academic_period', $e->validator->errors()->first());
        }
    }

    public function makeCurrent(int $termId, SetCurrentTerm $setCurrentTerm): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);

        $term = Term::findOrFail($termId);
        $setCurrentTerm->handle($term);
        unset($this->years);

        Flux::toast(variant: 'success', text: __(':term (:year) is now current.', ['term' => $term->name, 'year' => $term->academicYear->name]));
    }

    public function editTerm(int $termId): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);

        $term = Term::findOrFail($termId);
        $this->resetErrorBag();
        $this->editingTermId = $term->id;
        $this->editStartsOn = $term->starts_on->toDateString();
        $this->editEndsOn = $term->ends_on->toDateString();
        $this->editCensusOn = $term->census_on->toDateString();

        Flux::modal('edit-term')->show();
    }

    public function saveTerm(UpdateTermDates $updateTermDates): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);

        $term = Term::findOrFail($this->editingTermId);

        try {
            $updateTermDates->handle($term, $this->editStartsOn, $this->editEndsOn, $this->editCensusOn);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError(str_replace('term.', 'edit_', $field), $messages[0]);
            }

            return;
        }

        unset($this->years);
        Flux::modal('edit-term')->close();
        Flux::toast(variant: 'success', text: __('Dates saved.'));
    }

    public function deleteYear(int $yearId, DeleteAcademicYear $deleteAcademicYear): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);

        try {
            $deleteAcademicYear->handle(AcademicYear::findOrFail($yearId));
        } catch (ValidationException $e) {
            Flux::toast(variant: 'danger', text: $e->validator->errors()->first());

            return;
        }

        unset($this->years);
        Flux::toast(variant: 'success', text: __('Academic year deleted.'));
    }

    /**
     * @return list<AcademicPeriodType>
     */
    public function periodOptions(): array
    {
        return AcademicPeriodType::cases();
    }
}
