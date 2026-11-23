<?php

namespace Database\Seeders;

use App\Actions\Announcements\SendAnnouncement;
use App\Actions\Attendance\AttendanceRules;
use App\Actions\Attendance\TakeAttendance;
use App\Actions\Billing\IssueBill;
use App\Actions\Fees\CreateFeeItem;
use App\Actions\Fees\SaveFeeStructure;
use App\Actions\Payments\RecordPayment;
use App\Enums\CreditEntryType;
use App\Enums\GuardianAccess;
use App\Enums\Residence;
use App\Enums\RoleKey;
use App\Models\Announcement;
use App\Models\Bill;
use App\Models\FeeItem;
use App\Models\Guardian;
use App\Models\GuardianStudent;
use App\Models\MessageCreditEntry;
use App\Models\Payment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * A ready-to-try parent for the parent app on Greenhill (local and staging only):
 *
 * - a guardian with children in Primary 4A (the demo teacher's class) and another class, with a
 *   parent-app login (phone + DemoSchoolsSeeder::PASSWORD);
 * - this term's fees billed to those children, and a cash payment with a receipt;
 * - two weeks of register marks (one absence, one late) and a whole-school announcement;
 * - SMS credit, so sign-in codes and "forgot password" work with the log driver.
 *
 * Run after the demo schools: php artisan db:seed --class=DemoParentSeeder
 * (DatabaseSeeder runs it too). Running it again changes nothing already there.
 */
class DemoParentSeeder extends Seeder
{
    public function run(CurrentSchool $currentSchool): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoParentSeeder must never run in production.');
        }

        $school = School::where('subdomain', 'greenhill')->firstOrFail();

        $currentSchool->runFor($school, function (School $school): void {
            $admin = User::where('school_id', $school->id)->where('email', 'admin@greenhill.test')->firstOrFail();
            $term = Term::current()->firstOrFail();
            $guardian = $this->family();
            $children = $guardian->links()->with('student.currentEnrolment.stream.schoolClass')->get()
                ->map(fn (GuardianStudent $link) => $link->student)->sortBy('first_name')->values();

            $this->login($school, $guardian);
            $this->fees($term, array_values($children->all()), $admin);
            $this->attendance(array_values($children->all()), $admin);
            $this->announcement($admin);

            if (MessageCreditEntry::balance() < 50) {
                MessageCreditEntry::create(['type' => CreditEntryType::TopUp, 'units' => 100, 'note' => 'Demo credit (DemoParentSeeder)']);
            }

            $this->command?->info("Demo parent: {$guardian->name}, phone {$guardian->phone} (password: DemoSchoolsSeeder::PASSWORD)");
            $this->command?->info('Children: '.$children->map(fn (Student $s) => "{$s->full_name} ({$s->currentEnrolment?->stream->full_name})")->implode(', '));
        });
    }

    /**
     * The guardian with the most children, one of them in Primary 4A (the demo teacher's class).
     */
    private function family(): Guardian
    {
        $p4a = SchoolClass::where('name', 'Primary 4')->firstOrFail()->streams()->orderBy('name')->firstOrFail();

        $guardian = Guardian::whereHas('links.student.currentEnrolment', fn ($q) => $q->where('stream_id', $p4a->id))
            ->withCount('links')->orderByDesc('links_count')->orderBy('id')->firstOrFail();

        // Full access to every child, so fees and documents show.
        GuardianStudent::where('guardian_id', $guardian->id)->where('access_level', '!=', GuardianAccess::Full->value)
            ->get()->each(fn (GuardianStudent $link) => $link->forceFill(['access_level' => GuardianAccess::Full])->save());

        return $guardian;
    }

    private function login(School $school, Guardian $guardian): void
    {
        if ($guardian->user_id !== null) {
            return;
        }

        $user = User::where('school_id', $school->id)->where('phone', $guardian->phone)->first()
            ?? (new User(['name' => $guardian->name, 'password' => DemoSchoolsSeeder::PASSWORD]))->forceFill(['school_id' => $school->id, 'phone' => $guardian->phone]);
        $user->save();
        if (! $user->hasRole(RoleKey::Parent->value)) {
            $user->assignRole(RoleKey::Parent->value);
        }
        $guardian->user()->associate($user)->save();
    }

    /**
     * Tuition and PTA dues for this term for each child's class, billed, and one cash payment.
     *
     * @param  list<Student>  $children
     */
    private function fees(Term $term, array $children, User $admin): void
    {
        $tuition = FeeItem::where('name', 'Tuition')->first() ?? app(CreateFeeItem::class)->handle(['name' => 'Tuition', 'category' => 'tuition']);
        $pta = FeeItem::where('name', 'PTA dues')->first() ?? app(CreateFeeItem::class)->handle(['name' => 'PTA dues', 'category' => 'levy']);

        foreach ($children as $child) {
            $class = $child->currentEnrolment?->stream->schoolClass;
            if ($class === null || Bill::where('student_id', $child->id)->where('term_id', $term->id)->exists()) {
                continue;
            }
            app(SaveFeeStructure::class)->handle($term, $class, Residence::Day, [$tuition->id => '1200', $pta->id => '150']);
            app(IssueBill::class)->handle($child, $term);
        }

        $first = $children[0] ?? null;
        if ($first !== null && ! Payment::where('student_id', $first->id)->exists()) {
            app(RecordPayment::class)->handle($admin, $first, [
                'amount' => '500', 'method' => 'cash', 'paid_on' => today()->toDateString(), 'note' => 'Demo payment',
            ]);
        }
    }

    /**
     * The last ten school days' registers for each child's stream: the first child absent once,
     * the second late once, everyone else present.
     *
     * @param  list<Student>  $children
     */
    private function attendance(array $children, User $admin): void
    {
        $days = [];
        for ($day = CarbonImmutable::today(); count($days) < 10 && $day->diffInDays(today()) < 30; $day = $day->subDay()) {
            if (AttendanceRules::dayProblem($day) === null) {
                $days[] = $day;
            }
        }

        foreach (collect($children)->map(fn (Student $s) => $s->currentEnrolment?->stream)->filter()->unique('id') as $stream) {
            foreach ($days as $i => $day) {
                $marks = [];
                foreach (AttendanceRules::enrolmentsOn($stream, $day) as $enrolment) {
                    $status = 'present';
                    if ($i === 2 && $enrolment->student_id === ($children[0]->id ?? null)) {
                        $status = 'absent';
                    } elseif ($i === 4 && $enrolment->student_id === ($children[1]->id ?? null)) {
                        $status = 'late';
                    }
                    $marks[$enrolment->student_id] = ['status' => $status];
                }
                app(TakeAttendance::class)->handle($admin, $stream, $day->toDateString(), $marks, 'Demo data');
            }
        }
    }

    private function announcement(User $admin): void
    {
        if (Announcement::where('title', 'Welcome to the parent app')->exists()) {
            return;
        }

        app(SendAnnouncement::class)->handle($admin, [
            'title' => 'Welcome to the parent app',
            'body' => "You can now follow attendance, fees and school news here.\nDownload your child's fee statement and receipts under Fees.",
            'audience' => 'school',
        ]);
    }
}
