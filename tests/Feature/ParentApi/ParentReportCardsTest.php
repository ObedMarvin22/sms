<?php

require_once __DIR__.'/helpers.php';

use App\Actions\Results\ApproveReportCards;
use App\Actions\Results\PublishReportCards;
use App\Actions\Results\WithdrawReportCards;
use App\Enums\GuardianAccess;
use App\Enums\ReportCardStatus;
use App\Enums\RoleKey;
use App\Models\Notification;
use App\Models\ReportCard;
use App\Models\Stream;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TermResult;
use App\Notifications\ReportCardPublished;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/*
| The head approves and publishes a class's report cards; only then do parents see them in the
| parent app, with a notice, and can download the PDF. Full and limited access both may.
*/

function prcCard(Student $student, ReportCardStatus $status = ReportCardStatus::Generated): ReportCard
{
    $stream = $student->currentEnrolment->stream;
    $card = ReportCard::create([
        'term_id' => test()->term1->id, 'stream_id' => $stream->id, 'student_id' => $student->id,
        'status' => $status, 'average' => '71.50', 'class_size' => 3, 'subjects_count' => 1, 'position' => 2,
        'teacher_remark' => 'Works hard.', 'head_remark' => 'Very good work.',
    ]);
    TermResult::create([
        'term_id' => test()->term1->id, 'stream_id' => $stream->id, 'student_id' => $student->id,
        'subject_id' => Subject::factory()->for(test()->school)->create(['name' => 'Mathematics '.$student->id])->id,
        'total' => '71.50', 'grade' => 'P', 'grade_label' => 'Proficient', 'parts' => [],
    ]);

    return $card;
}

beforeEach(function () {
    setUpParentSchool();
    $this->head = staffWith(RoleKey::Headteacher);
    $this->ama = feeStudent('Ama');   // full access
    $this->kofi = feeStudent('Kofi'); // limited access, same parent
    $this->yaw = feeStudent('Yaw');   // another family's
    guardianWithChild(child: $this->ama);
    guardianWithChild(access: GuardianAccess::Limited, child: $this->kofi);
    guardianWithChild('+233244000222', child: $this->yaw);
    $this->stream = Stream::findOrFail($this->ama->currentEnrolment->stream_id);

    $this->cards = collect([$this->ama, $this->kofi, $this->yaw])->map(fn (Student $s) => prcCard($s));
});

describe('the head approves and publishes', function () {
    it('approves only when every card is made, then publishes with a notice to every guardian', function () {
        $this->cards[2]->update(['status' => ReportCardStatus::Draft]);
        expect(fn () => app(ApproveReportCards::class)->handle($this->head, $this->term1, $this->stream))
            ->toThrow(ValidationException::class, '1 report card is not made yet');
        expect(fn () => app(PublishReportCards::class)->handle($this->head, $this->term1, $this->stream))
            ->toThrow(ValidationException::class, 'before publishing');

        $this->cards[2]->update(['status' => ReportCardStatus::Generated]);
        expect(app(ApproveReportCards::class)->handle($this->head, $this->term1, $this->stream))->toBe(3)
            ->and(app(PublishReportCards::class)->handle($this->head, $this->term1, $this->stream))->toBe(3)
            ->and(ReportCard::where('status', ReportCardStatus::Published)->count())->toBe(3)
            ->and(Notification::where('type', ReportCardPublished::class)->count())->toBe(3); // one per child's guardian
    });

    it('is for heads only', function () {
        app(ApproveReportCards::class)->handle(staffWith(RoleKey::ClassTeacher), $this->term1, $this->stream);
    })->throws(AuthorizationException::class);
});

describe('the parent app', function () {
    beforeEach(function () {
        $this->withToken(activateParent());
    });

    it('shows nothing until the head publishes', function () {
        $this->getJson(api("children/{$this->ama->id}/report-cards"))->assertOk()->assertJsonCount(0, 'data');

        app(ApproveReportCards::class)->handle($this->head, $this->term1, $this->stream);
        $this->getJson(api("children/{$this->ama->id}/report-cards"))->assertJsonCount(0, 'data');
        $this->getJson(api("children/{$this->ama->id}/report-cards/{$this->cards[0]->id}.pdf"))->assertNotFound();
    });

    it('lists published report cards with the result, to full and limited access, but not another family', function () {
        app(ApproveReportCards::class)->handle($this->head, $this->term1, $this->stream);
        app(PublishReportCards::class)->handle($this->head, $this->term1, $this->stream);

        $this->getJson(api("children/{$this->ama->id}/report-cards"))->assertOk()
            ->assertJsonPath('data.0.term', $this->term1->name)
            ->assertJsonPath('data.0.average', 71.5)
            ->assertJsonPath('data.0.position', null) // the school does not show positions
            ->assertJsonPath('data.0.subjects.0.grade', 'P')
            ->assertJsonPath('data.0.head_remark', 'Very good work.');
        $this->getJson(api("children/{$this->kofi->id}/report-cards"))->assertOk()->assertJsonCount(1, 'data');
        $this->getJson(api("children/{$this->yaw->id}/report-cards"))->assertNotFound();

        $this->getJson(api('notifications'))->assertJsonPath('data.0.kind', 'report_card')
            ->assertJsonFragment(['message' => "Ama's report card for {$this->term1->name} is ready."]);
    });

    it('downloads the stored PDF, or makes it if the file is missing', function () {
        Storage::fake('local');
        Storage::disk('local')->put('report-cards/stored.pdf', '%PDF-1.4 stored');
        $this->cards[0]->update(['pdf_path' => 'report-cards/stored.pdf']);
        app(ApproveReportCards::class)->handle($this->head, $this->term1, $this->stream);
        app(PublishReportCards::class)->handle($this->head, $this->term1, $this->stream);

        $stored = $this->get(api("children/{$this->ama->id}/report-cards/{$this->cards[0]->id}.pdf"))->assertOk();
        expect($stored->headers->get('Content-Type'))->toBe('application/pdf')
            ->and($stored->getContent())->toBe('%PDF-1.4 stored');

        $made = $this->get(api("children/{$this->kofi->id}/report-cards/{$this->cards[1]->id}.pdf"))->assertOk();
        expect(substr((string) $made->getContent(), 0, 5))->toBe('%PDF-');

        // Another child's card through this child's address is not found.
        $this->get(api("children/{$this->ama->id}/report-cards/{$this->cards[1]->id}.pdf"))->assertNotFound();
    });

    it('loses access when the head withdraws the report cards', function () {
        app(ApproveReportCards::class)->handle($this->head, $this->term1, $this->stream);
        app(PublishReportCards::class)->handle($this->head, $this->term1, $this->stream);
        expect(app(WithdrawReportCards::class)->handle($this->head, $this->term1, $this->stream))->toBe(3);

        $this->getJson(api("children/{$this->ama->id}/report-cards"))->assertJsonCount(0, 'data');
        expect($this->cards[0]->fresh()->status)->toBe(ReportCardStatus::Generated);
    });
});
