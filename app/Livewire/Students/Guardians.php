<?php

namespace App\Livewire\Students;

use App\Actions\Guardians\AddGuardian;
use App\Actions\Guardians\FindGuardianByPhone;
use App\Actions\Guardians\LinkGuardian;
use App\Actions\Guardians\SetPrimaryGuardian;
use App\Actions\Guardians\UnlinkGuardian;
use App\Actions\Guardians\UpdateGuardian;
use App\Actions\Guardians\UpdateGuardianLink;
use App\Enums\GuardianAccess;
use App\Enums\GuardianRelationship;
use App\Enums\PermissionKey;
use App\Models\Guardian;
use App\Models\GuardianStudent;
use App\Models\Student;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The guardians section of a student's page. While adding, typing a phone that is already on
 * file switches the form to "link this guardian" (FindGuardianByPhone), so no duplicates.
 */
class Guardians extends Component
{
    #[Locked]
    public int $studentId;

    #[Locked]
    public ?int $editingLinkId = null;

    /** Guardian already on file with the typed phone, while adding. */
    #[Locked]
    public ?int $matchId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(int $studentId): void
    {
        $this->authorize(PermissionKey::StudentsView->value);
        $this->studentId = Student::findOrFail($studentId)->id;
        $this->resetForm();
    }

    public function canManage(): bool
    {
        return Auth::user()?->can(PermissionKey::StudentsManage->value) ?? false;
    }

    /**
     * @return Collection<int, GuardianStudent>
     */
    public function links(): Collection
    {
        return GuardianStudent::with('guardian')->where('student_id', $this->studentId)
            ->orderByDesc('is_primary')->orderBy('id')->get();
    }

    public function match(): ?Guardian
    {
        return $this->matchId === null ? null : Guardian::find($this->matchId);
    }

    public function updatedFormPhone(FindGuardianByPhone $find): void
    {
        if ($this->editingLinkId === null) {
            $this->matchId = $find->handle((string) $this->form['phone'])?->id;
        }
    }

    public function startAdd(): void
    {
        $this->authorize(PermissionKey::StudentsManage->value);
        $this->resetErrorBag();
        $this->resetForm();
        Flux::modal('guardian')->show();
    }

    public function startEdit(int $linkId): void
    {
        $this->authorize(PermissionKey::StudentsManage->value);
        $this->resetErrorBag();
        $link = $this->link($linkId);

        $this->resetForm();
        $this->editingLinkId = $link->id;
        $this->form = [
            'name' => $link->guardian->name,
            'phone' => $link->guardian->phone,
            'alt_phone' => (string) $link->guardian->alt_phone,
            'email' => (string) $link->guardian->email,
            'occupation' => (string) $link->guardian->occupation,
            'address' => (string) $link->guardian->address,
            'relationship' => $link->relationship->value,
            'access_level' => $link->access_level->value,
            'can_pick_up' => $link->can_pick_up,
        ];
        Flux::modal('guardian')->show();
    }

    public function save(AddGuardian $add, LinkGuardian $linkGuardian, UpdateGuardian $updateGuardian, UpdateGuardianLink $updateLink): void
    {
        $this->authorize(PermissionKey::StudentsManage->value);
        $this->resetErrorBag();
        $student = Student::findOrFail($this->studentId);

        try {
            if ($this->editingLinkId !== null) {
                $link = $this->link($this->editingLinkId);
                $updateGuardian->handle($link->guardian, $this->form);
                $updateLink->handle($link, $this->form);
            } elseif ($this->match() !== null) {
                $linkGuardian->handle($student, $this->match(), $this->form);
            } else {
                $add->handle($student, $this->form);
            }
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        Flux::modal('guardian')->close();
        Flux::toast(variant: 'success', text: __('Guardian saved.'));
        $this->resetForm();
    }

    public function makePrimary(int $linkId, SetPrimaryGuardian $setPrimary): void
    {
        $this->authorize(PermissionKey::StudentsManage->value);
        $setPrimary->handle($this->link($linkId));
        Flux::toast(variant: 'success', text: __('Primary contact changed.'));
    }

    public function unlink(int $linkId, UnlinkGuardian $unlinkGuardian): void
    {
        $this->authorize(PermissionKey::StudentsManage->value);
        $this->resetErrorBag();

        try {
            $unlinkGuardian->handle($this->link($linkId));
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        Flux::toast(variant: 'success', text: __('Guardian removed from this child.'));
    }

    /**
     * @return list<GuardianRelationship>
     */
    public function relationships(): array
    {
        return GuardianRelationship::cases();
    }

    /**
     * @return list<GuardianAccess>
     */
    public function accessLevels(): array
    {
        return GuardianAccess::cases();
    }

    public function render(): View
    {
        return view('livewire.students.guardians');
    }

    /**
     * A link of this student only (another child's link id is a 404).
     */
    private function link(int $linkId): GuardianStudent
    {
        return GuardianStudent::with('guardian')->where('student_id', $this->studentId)->findOrFail($linkId);
    }

    private function resetForm(): void
    {
        $this->editingLinkId = null;
        $this->matchId = null;
        $this->form = array_fill_keys(['name', 'phone', 'alt_phone', 'email', 'occupation', 'address', 'relationship'], '');
        $this->form['access_level'] = GuardianAccess::Full->value;
        $this->form['can_pick_up'] = true;
    }
}
