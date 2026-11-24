<?php

namespace App\Livewire\Grading;

use App\Actions\Grading\SaveGradeScale;
use App\Actions\Grading\SetUpDefaultGradeScales;
use App\Actions\Results\SetShowPositions;
use App\Enums\PermissionKey;
use App\Enums\SchoolLevel;
use App\Models\GradeBand;
use App\Models\GradeScale;
use App\Support\Academic\DefaultGradeScales;
use App\Support\Settings\SchoolSettings;
use App\Support\Tenancy\CurrentSchool;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Settings → Grading: each level's grade scale (bands of term totals).
 *
 * @property-read list<SchoolLevel> $levels
 * @property-read Collection<string, GradeScale> $scales
 */
#[Title('Grading')]
class Scales extends Component
{
    #[Locked]
    public ?string $editingLevel = null;

    public string $name = '';

    /** @var list<array{min_score: int|string, grade: string, label: string, points: int|string|null}> */
    public array $bands = [];

    public bool $showPositions = false;

    public function mount(): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);
        $this->showPositions = (bool) SchoolSettings::for(app(CurrentSchool::class)->require())->get('show_positions', false);
    }

    public function updatedShowPositions(SetShowPositions $set): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);
        $set->handle(app(CurrentSchool::class)->require(), $this->showPositions);
        Flux::toast(variant: 'success', text: $this->showPositions ? __('Positions will show on results and report cards.') : __('Positions are hidden.'));
    }

    /**
     * @return list<SchoolLevel>
     */
    #[Computed]
    public function levels(): array
    {
        return array_values(app(CurrentSchool::class)->require()->levels_offered
            ->sortBy(fn (SchoolLevel $l) => array_search($l, SchoolLevel::cases(), true))->all());
    }

    /**
     * @return Collection<string, GradeScale>
     */
    #[Computed]
    public function scales(): Collection
    {
        return GradeScale::with('bands')->get()->keyBy(fn (GradeScale $s) => $s->level->value)->toBase();
    }

    public function setUpDefaults(SetUpDefaultGradeScales $setUp): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);
        $created = $setUp->handle();
        unset($this->scales);

        Flux::toast(variant: 'success', text: $created > 0
            ? trans_choice('{1} :count scale set up.|[2,*] :count scales set up.', $created, ['count' => $created])
            : __('Every graded level already has a scale.'));
    }

    public function edit(string $level): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);
        $level = SchoolLevel::from($level);
        abort_unless(in_array($level, $this->levels, true), 404);
        $this->resetErrorBag();

        $scale = $this->scales->get($level->value);
        $default = DefaultGradeScales::for($level);
        $this->editingLevel = $level->value;
        $this->name = $scale->name ?? $default['name'] ?? $level->label().' grades';
        $this->bands = $scale !== null
            ? array_values($scale->bands->map(fn (GradeBand $b) => ['min_score' => $b->min_score, 'grade' => $b->grade, 'label' => $b->label, 'points' => $b->points])->all())
            : ($default['bands'] ?? [['min_score' => 50, 'grade' => 'P', 'label' => 'Pass', 'points' => null], ['min_score' => 0, 'grade' => 'F', 'label' => 'Fail', 'points' => null]]);

        Flux::modal('scale')->show();
    }

    public function addBand(): void
    {
        if (count($this->bands) < SaveGradeScale::MAX_BANDS) {
            $this->bands[] = ['min_score' => '', 'grade' => '', 'label' => '', 'points' => null];
        }
    }

    public function removeBand(int $index): void
    {
        array_splice($this->bands, $index, 1);
    }

    public function save(SaveGradeScale $save): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);
        $this->resetErrorBag();
        $level = SchoolLevel::from((string) $this->editingLevel);

        try {
            $save->handle($level, $this->name, $this->bands);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        unset($this->scales);
        Flux::modal('scale')->close();
        Flux::toast(variant: 'success', text: __(':level grades saved.', ['level' => $level->label()]));
    }

    public function render(): View
    {
        return view('livewire.grading.scales');
    }
}
