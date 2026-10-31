<?php

namespace App\Livewire\Announcements;

use App\Actions\Announcements\AnnouncementRules;
use App\Actions\Announcements\SendAnnouncement;
use App\Actions\Announcements\WithdrawAnnouncement;
use App\Enums\AnnouncementAudience;
use App\Enums\PermissionKey;
use App\Models\Announcement;
use App\Models\Stream;
use App\Models\User;
use Flux\Flux;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Announcements to parents: the list with how many families opened each, and sending a new one.
 */
#[Title('Announcements')]
class Index extends Component
{
    use WithPagination;

    /** @var array{title: string, body: string, audience: string, stream_ids: list<string>} */
    public array $form = ['title' => '', 'body' => '', 'audience' => 'streams', 'stream_ids' => []];

    public function mount(): void
    {
        $this->authorize(PermissionKey::AnnouncementsSend->value);
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    public function canSendToSchool(): bool
    {
        return AnnouncementRules::canSendToSchool($this->user());
    }

    /**
     * @return Collection<int, Stream>
     */
    #[Computed]
    public function streams(): Collection
    {
        return AnnouncementRules::streamsFor($this->user());
    }

    /**
     * @return LengthAwarePaginator<int, Announcement>
     */
    #[Computed]
    public function announcements(): LengthAwarePaginator
    {
        return Announcement::with(['sender', 'streams.schoolClass'])->latest('sent_at')->latest('id')->paginate(20);
    }

    /**
     * "Seen by" for the announcements on this page (the view passes the page it shows).
     *
     * @param  LengthAwarePaginator<int, Announcement>  $page
     * @return array<int, array{total: int, seen: int}>
     */
    public function seenFor(LengthAwarePaginator $page): array
    {
        $ids = [];
        foreach ($page->items() as $announcement) {
            $ids[] = $announcement->id;
        }

        return AnnouncementRules::seenCounts($ids);
    }

    public function canWithdraw(Announcement $announcement): bool
    {
        return $announcement->withdrawn_at === null && ($this->canSendToSchool() || $announcement->sent_by === $this->user()->id);
    }

    public function startCreate(): void
    {
        $this->resetErrorBag();
        $this->form = ['title' => '', 'body' => '', 'audience' => AnnouncementAudience::Streams->value, 'stream_ids' => []];
        Flux::modal('announcement')->show();
    }

    public function send(SendAnnouncement $sendAnnouncement): void
    {
        $this->authorize(PermissionKey::AnnouncementsSend->value);
        $this->resetErrorBag();

        try {
            $announcement = $sendAnnouncement->handle($this->user(), $this->form);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        } catch (AuthorizationException $e) {
            $this->addError('audience', $e->getMessage());

            return;
        }

        unset($this->announcements);
        Flux::modal('announcement')->close();
        Flux::toast(variant: 'success', text: __('":title" sent. Parents will see it in the app within a minute or two.', ['title' => $announcement->title]));
    }

    public function withdraw(int $id, WithdrawAnnouncement $withdrawAnnouncement): void
    {
        $this->authorize(PermissionKey::AnnouncementsSend->value);
        $withdrawAnnouncement->handle($this->user(), Announcement::findOrFail($id));

        unset($this->announcements);
        Flux::toast(text: __('Announcement withdrawn. Parents no longer see it.'));
    }

    public function render(): View
    {
        return view('livewire.announcements.index');
    }
}
