<?php

namespace App\Livewire\Invitations;

use App\Actions\Staff\AcceptInvitation as AcceptInvitationAction;
use App\Concerns\PasswordValidationRules;
use App\Models\User;
use App\Support\Invitations\InvitationLink;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Page behind the signed invitation link (route "invitation.accept", signed middleware).
 */
#[Layout('layouts::auth')]
#[Title('Set your password')]
class AcceptInvitation extends Component
{
    use PasswordValidationRules;

    #[Locked]
    public int $userId;

    #[Locked]
    public string $fingerprint;

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(User $user, string $fingerprint): void
    {
        $this->userId = $user->id;
        $this->fingerprint = $fingerprint;

        $this->invitee();
    }

    public function save(AcceptInvitationAction $acceptInvitation): void
    {
        $user = $this->invitee();

        $this->validate(['password' => $this->passwordRules()]);

        $acceptInvitation->handle($user, $this->password);

        Auth::guard('web')->login($user);
        session()->regenerate();

        $this->redirectRoute('dashboard', navigate: true);
    }

    /**
     * The invited user, if the invitation is for this school and still unused.
     */
    private function invitee(): User
    {
        $user = User::find($this->userId);

        // Route model binding is not school-scoped, so check membership here.
        abort_if($user === null || $user->school_id !== app(CurrentSchool::class)->id(), 404);
        abort_unless(InvitationLink::isUnused($user, $this->fingerprint), 403, 'This invitation has already been used.');

        return $user;
    }
}
