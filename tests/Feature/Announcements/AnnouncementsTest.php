<?php

require_once __DIR__.'/../ParentApi/helpers.php';
require_once __DIR__.'/../Attendance/helpers.php';

use App\Actions\Announcements\NotifyAnnouncementGuardians;
use App\Actions\Announcements\SendAnnouncement;
use App\Actions\Announcements\WithdrawAnnouncement;
use App\Enums\GuardianAccess;
use App\Enums\RoleKey;
use App\Jobs\SendAnnouncementNotices;
use App\Livewire\Announcements\Index;
use App\Models\Announcement;
use App\Models\Guardian;
use App\Models\Notification;
use App\Models\School;
use App\Notifications\AnnouncementPosted;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/*
| Sprint 4 step 8: announcements. Heads and administrators send to the whole school or any
| class; class teachers to their own streams only. Parents read them in the app.
*/

beforeEach(function () {
    setUpParentSchool(); // SMS credit for the parent's sign-in code
    $this->p1a = $this->p1->streams()->sole();
    $this->p2a = $this->p2->streams()->sole();

    $this->ama = pupil('Ama', $this->p1a);
    $this->kofi = pupil('Kofi', $this->p2a);
    $this->esi = pupil('Esi', $this->p1a);

    $this->mother = guardianWithChild(child: $this->ama);                                 // full, P1
    $this->father = guardianWithChild('+233244000222', GuardianAccess::Limited, $this->kofi); // limited, P2
    $this->contact = guardianWithChild('+233244000333', GuardianAccess::None, $this->esi);   // contact only

    $this->head = staffWith(RoleKey::Headteacher);
    $this->p1Teacher = classTeacherOf($this->p1a);
});

function announce(array $input = [], $by = null): Announcement
{
    return app(SendAnnouncement::class)->handle($by ?? test()->head, array_merge(
        ['title' => 'PTA meeting', 'body' => "Saturday at 9am.\nIn the hall.", 'audience' => 'school'],
        $input,
    ));
}

function noticeCount(Guardian $guardian): int
{
    return $guardian->notifications()->where('type', AnnouncementPosted::class)->count();
}

describe('sending', function () {
    it('lets a head send to the whole school, reaching every family above contact only', function () {
        $announcement = announce();

        expect($announcement->sent_by)->toBe($this->head->id)
            ->and(noticeCount($this->mother))->toBe(1)
            ->and(noticeCount($this->father))->toBe(1)   // limited access still gets notices
            ->and(noticeCount($this->contact))->toBe(0);
    });

    it('reaches only the chosen streams', function () {
        announce(['audience' => 'streams', 'stream_ids' => [$this->p2a->id]]);

        expect(noticeCount($this->father))->toBe(1)
            ->and(noticeCount($this->mother))->toBe(0);
    });

    it('lets a class teacher send to their own stream', function () {
        announce(['audience' => 'streams', 'stream_ids' => [$this->p1a->id]], $this->p1Teacher);

        expect(noticeCount($this->mother))->toBe(1)->and(noticeCount($this->father))->toBe(0);
    });

    it('refuses a class teacher another stream', function () {
        expect(fn () => announce(['audience' => 'streams', 'stream_ids' => [$this->p2a->id]], $this->p1Teacher))
            ->toThrow(ValidationException::class, 'You can only send to your own classes.');
    });

    it('refuses a class teacher the whole school', function () {
        expect(fn () => announce(['audience' => 'school'], $this->p1Teacher))
            ->toThrow(AuthorizationException::class, 'Only heads and administrators');
        expect(Announcement::count())->toBe(0);
    });

    it('refuses staff without the permission, and class teachers with no class', function (RoleKey $role) {
        expect(fn () => announce([], staffWith($role)))->toThrow(AuthorizationException::class);
    })->with([RoleKey::SubjectTeacher, RoleKey::Bursar, RoleKey::ClassTeacher]);

    it('needs a title, a message and at least one class', function () {
        expect(fn () => announce(['title' => '']))->toThrow(ValidationException::class)
            ->and(fn () => announce(['body' => str_repeat('a', 2001)]))->toThrow(ValidationException::class)
            ->and(fn () => announce(['audience' => 'streams', 'stream_ids' => []]))->toThrow(ValidationException::class, 'Choose at least one class.');
    });

    it('queues the notices in chunks', function () {
        Queue::fake();
        foreach (range(1, 4) as $i) {
            guardianWithChild('+23324400'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), child: pupil("Child{$i}", $this->p1a));
        }

        $announcement = announce();

        // 6 families with app access (the contact-only one is left out).
        Queue::assertPushed(SendAnnouncementNotices::class, fn ($job) => $job->announcementId === $announcement->id && $job->schoolId() === $this->school->id);
        expect(Queue::pushed(SendAnnouncementNotices::class)->sum(fn ($job) => count($job->guardianIds)))->toBe(6);
    });

    it('gives each family the notice once, even if the job runs again', function () {
        $announcement = announce();

        app(NotifyAnnouncementGuardians::class)->handle($announcement->id, [$this->mother->id, $this->father->id]);

        expect(noticeCount($this->mother))->toBe(1)->and(noticeCount($this->father))->toBe(1);
    });

    it('logs the announcement', function () {
        $announcement = announce();

        expect(Activity::where('log_name', 'announcements')->where('subject_id', $announcement->id)->where('event', 'created')->exists())->toBeTrue();
    });
});

describe('withdrawing', function () {
    it('hides it, removes its notices and logs who did it', function () {
        $announcement = announce();

        app(WithdrawAnnouncement::class)->handle($this->head, $announcement);

        expect($announcement->fresh()->withdrawn_at)->not->toBeNull()
            ->and($announcement->fresh()->withdrawn_by)->toBe($this->head->id)
            ->and(noticeCount($this->mother))->toBe(0)
            ->and(Activity::where('log_name', 'announcements')->where('event', 'updated')->where('subject_id', $announcement->id)->exists())->toBeTrue();
    });

    it('sends no notices once withdrawn, even from a late job', function () {
        Queue::fake();
        $announcement = announce();
        app(WithdrawAnnouncement::class)->handle($this->head, $announcement);

        expect(app(NotifyAnnouncementGuardians::class)->handle($announcement->id, [$this->mother->id]))->toBe(0);
    });

    it('lets a class teacher withdraw only their own', function () {
        $theirs = announce(['audience' => 'streams', 'stream_ids' => [$this->p1a->id]], $this->p1Teacher);
        $heads = announce();

        app(WithdrawAnnouncement::class)->handle($this->p1Teacher, $theirs);
        expect(fn () => app(WithdrawAnnouncement::class)->handle($this->p1Teacher, $heads))->toThrow(AuthorizationException::class);
    });
});

describe('the staff screen', function () {
    it('lists announcements with how many families opened them', function () {
        $announcement = announce();
        $this->mother->notifications()->where('type', AnnouncementPosted::class)->update(['read_at' => now()]);

        $this->actingAs($this->head);
        Livewire::test(Index::class)
            ->assertSee('PTA meeting')
            ->assertSee('1 of 2 families');
    });

    it('sends from the form', function () {
        $this->actingAs($this->p1Teacher);

        Livewire::test(Index::class)
            ->call('startCreate')
            ->set('form.title', 'Class trip')
            ->set('form.body', 'Bring a packed lunch.')
            ->set('form.stream_ids', [(string) $this->p1a->id])
            ->call('send')
            ->assertHasNoErrors();

        expect(Announcement::sole()->title)->toBe('Class trip')
            ->and(noticeCount($this->mother))->toBe(1);
    });

    it('shows a class teacher only their own streams to choose', function () {
        $this->actingAs($this->p1Teacher);

        $component = Livewire::test(Index::class);
        expect($component->instance()->streams()->modelKeys())->toBe([$this->p1a->id]);
    });

    it('is refused without the permission', function () {
        $this->actingAs(staffWith(RoleKey::Bursar))
            ->get('http://greenhill.localhost/announcements')
            ->assertForbidden();
    });
});

describe('the parent app', function () {
    beforeEach(function () {
        $this->withToken(activateParent()); // the mother: Ama in P1
    });

    it('lists whole-school announcements and those for her children, newest first', function () {
        $this->travel(-2)->hours();
        announce(['title' => 'School closes early']);
        $this->travel(1)->hours();
        announce(['title' => 'P1 trip', 'audience' => 'streams', 'stream_ids' => [$this->p1a->id]]);
        announce(['title' => 'P2 trip', 'audience' => 'streams', 'stream_ids' => [$this->p2a->id]]);

        $data = $this->getJson(api('announcements'))->assertOk()->json('data');

        expect(array_column($data, 'title'))->toBe(['P1 trip', 'School closes early'])
            ->and($data[0])->toMatchArray(['whole_school' => false, 'concerns' => ['Ama']])
            ->and($data[1])->toMatchArray(['whole_school' => true, 'concerns' => []]);
    });

    it("is not found for another class's announcement or a withdrawn one", function () {
        $p2 = announce(['audience' => 'streams', 'stream_ids' => [$this->p2a->id]]);
        $gone = announce();
        app(WithdrawAnnouncement::class)->handle($this->head, $gone);

        $this->getJson(api("announcements/{$p2->id}"))->assertNotFound();
        $this->getJson(api("announcements/{$gone->id}"))->assertNotFound();
        expect($this->getJson(api('announcements'))->json('data'))->toBe([]);
    });

    it('marks its notice read when opened, keeping line breaks in the text', function () {
        $announcement = announce();
        expect($this->getJson(api('notifications'))->json('unread'))->toBe(1);

        $this->getJson(api("announcements/{$announcement->id}"))
            ->assertOk()
            ->assertJsonPath('data.body', "Saturday at 9am.\nIn the hall.");

        expect($this->getJson(api('notifications'))->json('unread'))->toBe(0)
            ->and($this->father->notifications()->whereNull('read_at')->count())->toBe(1) // only her own notice
            ->and($this->getJson(api('notifications'))->json('data.0'))->toMatchArray(['kind' => 'announcement', 'announcement_id' => $announcement->id]);
    });

    it("never shows another school's announcements", function () {
        $hillview = School::factory()->active()->create(['subdomain' => 'hillview']);
        app(CurrentSchool::class)->runFor($hillview, fn () => Announcement::factory()->create(['school_id' => $hillview->id, 'title' => 'Hillview news']));

        expect($this->getJson(api('announcements'))->json('data'))->toBe([]);
    });

    it('shows nothing to a family whose children are all contact only', function () {
        announce();
        Notification::query()->delete();
        $this->mother->links()->update(['access_level' => GuardianAccess::None->value]);

        expect($this->getJson(api('announcements'))->json('data'))->toBe([]);
    });
});
