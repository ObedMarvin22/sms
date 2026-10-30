<?php

namespace App\Livewire\Staff;

use App\Actions\Staff\CreateStaff;
use App\Actions\Staff\MarkStaffLeft;
use App\Actions\Staff\ResendInvitation;
use App\Actions\Staff\SetStaffRoles;
use App\Actions\Staff\UpdateStaff;
use App\Enums\Gender;
use App\Enums\PermissionKey;
use App\Enums\RoleKey;
use App\Models\Staff;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Staff')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public bool $showLeft = false;

    #[Locked]
    public ?int $editingId = null;

    /** @var array<string, string> */
    public array $form = [];

    /** @var list<string> */
    public array $roles = [];

    public function mount(): void
    {
        $this->authorize(PermissionKey::StaffManage->value);
        $this->resetForm();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Staff>
     */
    #[Computed]
    public function staff(): LengthAwarePaginator
    {
        $term = trim($this->search);

        return Staff::query()
            ->with('user')
            ->when(! $this->showLeft, fn ($q) => $q->where('status', 'active'))
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('staff_number', 'like', "%{$term}%")))
            ->orderBy('last_name')->orderBy('first_name')
            ->paginate(25);
    }

    /**
     * Roles the signed-in user could give (Parent never; see StaffRules).
     *
     * @return list<RoleKey>
     */
    public function grantableRoles(): array
    {
        return [
            RoleKey::Proprietor, RoleKey::Headteacher, RoleKey::Administrator, RoleKey::Bursar,
            RoleKey::ClassTeacher, RoleKey::SubjectTeacher, RoleKey::HouseMaster, RoleKey::GateStaff,
        ];
    }

    public function canManageRoles(): bool
    {
        return Auth::user()?->can(PermissionKey::RolesManage->value) ?? false;
    }

    public function startCreate(): void
    {
        $this->authorize(PermissionKey::StaffManage->value);
        $this->resetErrorBag();
        $this->resetForm();
        Flux::modal('staff')->show();
    }

    public function startEdit(int $id): void
    {
        $this->authorize(PermissionKey::StaffManage->value);
        $this->resetErrorBag();
        $staff = Staff::with('user')->findOrFail($id);

        $this->editingId = $staff->id;
        $this->form = [
            'staff_number' => (string) $staff->staff_number,
            'first_name' => $staff->first_name,
            'last_name' => $staff->last_name,
            'other_names' => (string) $staff->other_names,
            'gender' => (string) $staff->gender?->value,
            'phone' => (string) $staff->phone,
            'email' => (string) $staff->email,
            'job_title' => (string) $staff->job_title,
            'employed_on' => (string) $staff->employed_on?->toDateString(),
        ];
        $this->roles = $staff->user ? array_values($staff->user->getRoleNames()->all()) : [];

        Flux::modal('staff')->show();
    }

    public function save(CreateStaff $createStaff, UpdateStaff $updateStaff, SetStaffRoles $setStaffRoles): void
    {
        $this->authorize(PermissionKey::StaffManage->value);
        $this->resetErrorBag();
        $actor = $this->actor();
        $rolesAllowed = $this->canManageRoles();

        // No wrapping transaction here: each Action commits on its own and only then sends
        // invitations, so an email never goes out for a save that is later rolled back.
        try {
            if ($this->editingId === null) {
                $createStaff->handle($this->form, $rolesAllowed ? $this->roles : [], $actor);
            } else {
                $staff = $updateStaff->handle(Staff::findOrFail($this->editingId), $this->form)->fresh();
                if ($rolesAllowed && $staff?->user !== null) {
                    $setStaffRoles->handle($staff, $this->roles, $actor);
                }
            }
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        unset($this->staff);
        Flux::modal('staff')->close();
        Flux::toast(variant: 'success', text: __('Staff saved.'));
    }

    public function resendInvitation(int $id, ResendInvitation $resendInvitation): void
    {
        $this->authorize(PermissionKey::StaffManage->value);

        try {
            $resendInvitation->handle(Staff::findOrFail($id));
            Flux::toast(variant: 'success', text: __('Invitation sent again.'));
        } catch (ValidationException $e) {
            Flux::toast(variant: 'danger', text: $e->validator->errors()->first());
        }
    }

    public function markLeft(int $id, MarkStaffLeft $markStaffLeft): void
    {
        $this->authorize(PermissionKey::StaffManage->value);

        try {
            $markStaffLeft->handle(Staff::findOrFail($id), $this->actor());
        } catch (ValidationException $e) {
            Flux::toast(variant: 'danger', text: $e->validator->errors()->first());

            return;
        }

        unset($this->staff);
        Flux::toast(variant: 'success', text: __('Marked as left. Their login is switched off.'));
    }

    /**
     * @return list<Gender>
     */
    public function genders(): array
    {
        return Gender::cases();
    }

    public function render(): View
    {
        return view('livewire.staff.index');
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->form = array_fill_keys(['staff_number', 'first_name', 'last_name', 'other_names', 'gender', 'phone', 'email', 'job_title', 'employed_on'], '');
        $this->roles = [];
    }

    private function actor(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
