<?php

namespace App\Livewire\Students;

use App\Actions\Attendance\AttendanceRules;
use App\Actions\Students\BuildClassList;
use App\Enums\PermissionKey;
use App\Models\Stream;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Today's students in one stream, with their primary contact; downloadable as a PDF.
 */
#[Title('Class lists')]
class ClassList extends Component
{
    #[Url(as: 'stream')]
    public string $streamId = '';

    public function mount(): void
    {
        $this->authorize(PermissionKey::StudentsView->value);

        $streams = $this->streams();
        if ($this->streamId === '' || ! $streams->contains('id', (int) $this->streamId)) {
            // A class teacher starts on their own stream.
            $own = AttendanceRules::classTeacherStreamIds($this->user());
            $this->streamId = (string) ($streams->firstWhere('id', $own[0] ?? 0)->id ?? $streams->first()->id ?? '');
        }
    }

    /**
     * @return Collection<int, Stream>
     */
    #[Computed]
    public function streams(): Collection
    {
        return Stream::query()->select('streams.*')
            ->join('school_classes', 'school_classes.id', '=', 'streams.school_class_id')
            ->with('schoolClass')
            ->orderBy('school_classes.sequence')->orderBy('streams.name')
            ->get();
    }

    public function selectedStream(): ?Stream
    {
        return $this->streams()->firstWhere('id', (int) $this->streamId);
    }

    /**
     * @return array{rows: list<array<string, mixed>>, boys: int, girls: int, total: int}|null
     */
    public function classList(): ?array
    {
        $stream = $this->selectedStream();

        return $stream === null ? null : app(BuildClassList::class)->handle($stream);
    }

    public function render(): View
    {
        return view('livewire.students.class-list');
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
