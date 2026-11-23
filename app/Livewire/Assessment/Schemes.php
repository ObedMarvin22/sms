<?php

namespace App\Livewire\Assessment;

use App\Actions\Assessment\CopySchemesFromYear;
use App\Actions\Assessment\SaveAssessmentScheme;
use App\Actions\Assessment\SetUpDefaultSchemes;
use App\Enums\AssessmentMode;
use App\Enums\ComponentKind;
use App\Enums\PermissionKey;
use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\AssessmentComponent;
use App\Models\AssessmentScheme;
use App\Support\Tenancy\CurrentSchool;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Settings → Assessment: how each level is marked in a year (continuous assessment and exam).
 *
 * @property-read Collection<int, AcademicYear> $years
 * @property-read AcademicYear|null $year
 * @property-read list<SchoolLevel> $levels
 * @property-read \Illuminate\Support\Collection<string, AssessmentScheme> $schemes
 * @property-read AcademicYear|null $copySource
 */
#[Title('Assessment')]
class Schemes extends Component
{
    #[Url(as: 'year')]
    public ?int $yearId = null;

    #[Locked]
    public ?string $editingLevel = null;

    public string $mode = 'weighted';

    /** @var list<array{id: int|null, name: string, kind: string, weight: int|string}> */
    public array $parts = [];

    public function mount(): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);

        if ($this->yearId === null || ! AcademicYear::whereKey($this->yearId)->exists()) {
            $this->yearId = (AcademicYear::current()->first() ?? AcademicYear::orderByDesc('starts_on')->first())?->id;
        }
    }

    /**
     * @return Collection<int, AcademicYear>
     */
    #[Computed]
    public function years(): Collection
    {
        return AcademicYear::orderByDesc('starts_on')->get();
    }

    #[Computed]
    public function year(): ?AcademicYear
    {
        return $this->years->firstWhere('id', $this->yearId);
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
     * @return \Illuminate\Support\Collection<string, AssessmentScheme>
     */
    #[Computed]
    public function schemes(): \Illuminate\Support\Collection
    {
        return AssessmentScheme::with('components')->where('academic_year_id', $this->yearId)->get()
            ->keyBy(fn (AssessmentScheme $s) => $s->level->value)->toBase();
    }

    /**
     * The latest other year with schemes, to offer copying from.
     */
    #[Computed]
    public function copySource(): ?AcademicYear
    {
        return AcademicYear::whereKeyNot($this->yearId)->whereIn('id', AssessmentScheme::select('academic_year_id'))
            ->orderByDesc('starts_on')->first();
    }

    public function updatedYearId(): void
    {
        $this->forget();
    }

    public function setUpDefaults(SetUpDefaultSchemes $setUp): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);
        $created = $setUp->handle($this->requireYear());
        $this->forget();

        Flux::toast(variant: 'success', text: $created > 0
            ? trans_choice('{1} :count level set up.|[2,*] :count levels set up.', $created, ['count' => $created])
            : __('Every level already has a scheme.'));
    }

    public function copyFromPrevious(CopySchemesFromYear $copy): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);
        $from = $this->copySource;
        abort_if($from === null, 404);

        $copied = $copy->handle($from, $this->requireYear());
        $this->forget();

        Flux::toast(variant: 'success', text: $copied > 0
            ? trans_choice('{1} Copied :count level from :year.|[2,*] Copied :count levels from :year.', $copied, ['count' => $copied, 'year' => $from->name])
            : __('Nothing to copy: these levels are already set up.'));
    }

    public function edit(string $level): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);
        $level = SchoolLevel::from($level);
        abort_unless(in_array($level, $this->levels, true), 404);
        $this->resetErrorBag();

        $scheme = $this->schemes->get($level->value);
        $this->editingLevel = $level->value;
        $this->mode = ($scheme->mode ?? AssessmentMode::defaultFor($level))->value;
        $this->parts = $scheme !== null && $scheme->components->isNotEmpty()
            ? array_values($scheme->components->map(fn (AssessmentComponent $c) => ['id' => $c->id, 'name' => $c->name, 'kind' => $c->kind->value, 'weight' => $c->weight])->all())
            : array_map(fn (array $p) => ['id' => null, 'name' => $p['name'], 'kind' => $p['kind']->value, 'weight' => $p['weight']], SetUpDefaultSchemes::WEIGHTED);

        Flux::modal('scheme')->show();
    }

    public function addPart(): void
    {
        if (count($this->parts) < SaveAssessmentScheme::MAX_PARTS) {
            $this->parts[] = ['id' => null, 'name' => '', 'kind' => ComponentKind::Other->value, 'weight' => ''];
        }
    }

    public function removePart(int $index): void
    {
        array_splice($this->parts, $index, 1);
    }

    public function save(SaveAssessmentScheme $save): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);
        $this->resetErrorBag();
        $level = SchoolLevel::from((string) $this->editingLevel);

        try {
            $save->handle($this->requireYear(), $level, AssessmentMode::from($this->mode), $this->parts);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $this->forget();
        Flux::modal('scheme')->close();
        Flux::toast(variant: 'success', text: __(':level assessment saved.', ['level' => $level->label()]));
    }

    public function total(): int
    {
        return array_sum(array_map(fn (array $p) => (int) $p['weight'], $this->parts));
    }

    public function render(): View
    {
        return view('livewire.assessment.schemes');
    }

    private function requireYear(): AcademicYear
    {
        return $this->year ?? abort(404);
    }

    private function forget(): void
    {
        unset($this->year, $this->schemes, $this->copySource);
    }
}
