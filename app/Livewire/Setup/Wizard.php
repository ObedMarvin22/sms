<?php

namespace App\Livewire\Setup;

use App\Actions\Classes\ApplyDefaultClassSubjects;
use App\Actions\Classes\GenerateDefaultClasses;
use App\Actions\Schools\MarkSetupComplete;
use App\Actions\Subjects\GenerateDefaultSubjects;
use App\Enums\PermissionKey;
use App\Enums\SetupStep;
use App\Support\Setup\SetupProgress;
use App\Support\Tenancy\CurrentSchool;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Walks a new school through setup in order. Each step opens the screen built for it;
 * the "quick" buttons run the same Actions those screens use.
 */
#[Title('Set up your school')]
class Wizard extends Component
{
    public function mount(): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);
        $this->recordCompletion();
    }

    public function generateClasses(GenerateDefaultClasses $generate): void
    {
        $this->quick(SetupStep::Classes, fn () => $generate->handle());
    }

    public function generateSubjects(GenerateDefaultSubjects $generate): void
    {
        $this->quick(SetupStep::Subjects, fn () => $generate->handle());
    }

    public function applyClassSubjects(ApplyDefaultClassSubjects $apply): void
    {
        $this->quick(SetupStep::ClassSubjects, fn () => $apply->handle());
    }

    public function render(SetupProgress $progress): View
    {
        return view('livewire.setup.wizard', [
            'steps' => SetupStep::cases(),
            'progress' => $progress,
        ]);
    }

    /**
     * Run a quick action, but only when its step is open (steps go in order).
     */
    private function quick(SetupStep $step, callable $action): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);
        abort_unless(app(SetupProgress::class)->isOpen($step), 403);

        $action();
        $this->recordCompletion();
        Flux::toast(variant: 'success', text: __(':step: done.', ['step' => __($step->label())]));
    }

    private function recordCompletion(): void
    {
        if (app(SetupProgress::class)->isComplete()) {
            app(MarkSetupComplete::class)->handle(app(CurrentSchool::class)->require());
        }
    }
}
