<?php

namespace App\Livewire\Assessment;

use App\Actions\Assessment\BuildProgressOverview;
use App\Enums\PermissionKey;
use App\Enums\SheetProgress;
use App\Models\Term;
use App\Support\Assessment\ProgressCell;
use App\Support\Assessment\ProgressOverview;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Assessment → Progress: every class and subject's mark sheet this term, for heads.
 *
 * @property-read Collection<int, Term> $terms
 * @property-read Term|null $term
 * @property-read ProgressOverview $overview
 */
#[Title('Marks progress')]
class Progress extends Component
{
    #[Url(as: 'term')]
    public ?int $termId = null;

    #[Url(except: '')]
    public string $level = '';

    /** '' for all, 'outstanding' for everything not submitted, or a SheetProgress value */
    #[Url(except: '')]
    public string $state = '';

    #[Url(except: '')]
    public string $teacher = '';

    public function mount(): void
    {
        $this->authorize(PermissionKey::GradesApprove->value);

        if ($this->termId === null || ! Term::whereKey($this->termId)->exists()) {
            $this->termId = Term::current()->value('id');
        }
    }

    /**
     * @return Collection<int, Term>
     */
    #[Computed]
    public function terms(): Collection
    {
        return Term::with('academicYear')->orderByDesc('starts_on')->get();
    }

    #[Computed]
    public function term(): ?Term
    {
        return $this->terms->firstWhere('id', $this->termId);
    }

    #[Computed]
    public function overview(): ProgressOverview
    {
        return $this->term === null
            ? new ProgressOverview([], [])
            : app(BuildProgressOverview::class)->handle($this->term);
    }

    public function matches(ProgressCell $cell): bool
    {
        return ($this->state === ''
                || ($this->state === 'outstanding' && $cell->progress->isOutstanding())
                || $this->state === $cell->progress->value
                || ($this->state === 'no_teacher' && $cell->teacherId === null))
            && ($this->teacher === '' || (string) $cell->teacherId === $this->teacher);
    }

    public function filtering(): bool
    {
        return $this->state !== '' || $this->teacher !== '';
    }

    /**
     * Teachers who have at least one sheet this term, for the filter.
     *
     * @return array<int, string> staff id => name
     */
    public function teacherOptions(): array
    {
        $options = [];
        foreach ($this->overview->cells() as $cell) {
            if ($cell->teacherId !== null) {
                $options[$cell->teacherId] = (string) $cell->teacher;
            }
        }
        asort($options);

        return $options;
    }

    public function updatedTermId(): void
    {
        unset($this->term, $this->overview);
    }

    public function clearFilters(): void
    {
        $this->reset('level', 'state', 'teacher');
    }

    public function render(): View
    {
        return view('livewire.assessment.progress', ['progressCases' => SheetProgress::cases()]);
    }
}
