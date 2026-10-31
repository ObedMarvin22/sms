<?php

use App\Models\AcademicYear;
use App\Models\AdmissionSequence;
use App\Models\Announcement;
use App\Models\AnnouncementStream;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Bill;
use App\Models\BillingRun;
use App\Models\BillInstalment;
use App\Models\BillLine;
use App\Models\ClassSubject;
use App\Models\ClassTeacherAssignment;
use App\Models\Discount;
use App\Models\Enrolment;
use App\Models\FeeItem;
use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Models\Guardian;
use App\Models\GuardianStudent;
use App\Models\InstalmentPlan;
use App\Models\InstalmentPlanStep;
use App\Models\LedgerEntry;
use App\Models\MessageCreditEntry;
use App\Models\Notification;
use App\Models\ParentOtp;
use App\Models\Payment;
use App\Models\PaystackTransaction;
use App\Models\Programme;
use App\Models\ProgrammeSubject;
use App\Models\ReceiptSequence;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SmsMessage;
use App\Models\Staff;
use App\Models\Stream;
use App\Models\Student;
use App\Models\StudentDiscount;
use App\Models\StudentFeeOption;
use App\Models\StudentImport;
use App\Models\StudentImportRow;
use App\Models\StudentInstalmentPlan;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

/*
| SPEC section 4: every school-owned model gets an isolation test. Add each new model to the
| dataset below with a closure that creates one record for the given school.
*/
dataset('school-owned models', [
    'AcademicYear' => [fn (School $school): Model => AcademicYear::factory()->for($school)->create()],
    'Term' => [fn (School $school): Model => Term::factory()->for(AcademicYear::factory()->for($school))->create()],
    'SchoolClass' => [fn (School $school): Model => SchoolClass::factory()->for($school)->create()],
    'Stream' => [fn (School $school): Model => Stream::factory()->for(SchoolClass::factory()->for($school))->create()],
    'Subject' => [fn (School $school): Model => Subject::factory()->for($school)->create()],
    'Enrolment' => [fn (School $school): Model => Enrolment::factory()->create(['school_id' => $school->id])],
    'Guardian' => [fn (School $school): Model => Guardian::factory()->for($school)->create()],
    'GuardianStudent' => [fn (School $school): Model => GuardianStudent::factory()->create(['school_id' => $school->id])],
    'Student' => [fn (School $school): Model => Student::factory()->for($school)->create()],
    'StudentImport' => [fn (School $school): Model => StudentImport::factory()->for($school)->create()],
    'StudentImportRow' => [fn (School $school): Model => StudentImportRow::factory()->create(['school_id' => $school->id])],
    'AttendanceSession' => [fn (School $school): Model => AttendanceSession::factory()->create(['school_id' => $school->id])],
    'AttendanceRecord' => [fn (School $school): Model => AttendanceRecord::factory()->create(['school_id' => $school->id])],
    'FeeItem' => [fn (School $school): Model => FeeItem::factory()->for($school)->create()],
    'Discount' => [fn (School $school): Model => Discount::factory()->for($school)->create()],
    'Payment' => [fn (School $school): Model => Payment::factory()->create(['school_id' => $school->id])],
    'Announcement' => [fn (School $school): Model => Announcement::factory()->for($school)->create()],
    'AnnouncementStream' => [fn (School $school): Model => AnnouncementStream::factory()->create(['school_id' => $school->id])],
    'ParentOtp' => [fn (School $school): Model => ParentOtp::factory()->for($school)->create()],
    'SmsMessage' => [fn (School $school): Model => SmsMessage::factory()->for($school)->create()],
    'MessageCreditEntry' => [fn (School $school): Model => MessageCreditEntry::factory()->for($school)->create()],
    'PaystackTransaction' => [fn (School $school): Model => PaystackTransaction::factory()->create(['school_id' => $school->id])],
    'ReceiptSequence' => [fn (School $school): Model => ReceiptSequence::factory()->for($school)->create()],
    'Bill' => [fn (School $school): Model => Bill::factory()->create(['school_id' => $school->id])],
    'BillLine' => [fn (School $school): Model => BillLine::factory()->create(['school_id' => $school->id])],
    'BillInstalment' => [fn (School $school): Model => BillInstalment::factory()->create(['school_id' => $school->id])],
    'BillingRun' => [fn (School $school): Model => BillingRun::factory()->create(['school_id' => $school->id])],
    'LedgerEntry' => [fn (School $school): Model => LedgerEntry::factory()->create(['school_id' => $school->id])],
    'StudentDiscount' => [fn (School $school): Model => StudentDiscount::factory()->create(['school_id' => $school->id])],
    'InstalmentPlan' => [fn (School $school): Model => InstalmentPlan::factory()->create(['school_id' => $school->id])],
    'InstalmentPlanStep' => [fn (School $school): Model => InstalmentPlanStep::factory()->create(['school_id' => $school->id])],
    'StudentInstalmentPlan' => [fn (School $school): Model => StudentInstalmentPlan::factory()->create(['school_id' => $school->id])],
    'FeeStructure' => [fn (School $school): Model => FeeStructure::factory()->create(['school_id' => $school->id])],
    'FeeStructureItem' => [fn (School $school): Model => FeeStructureItem::factory()->create(['school_id' => $school->id])],
    'StudentFeeOption' => [fn (School $school): Model => StudentFeeOption::factory()->create(['school_id' => $school->id])],
    'Notification' => [fn (School $school): Model => Notification::create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'type' => 'test',
        'notifiable_type' => Guardian::class,
        'notifiable_id' => Guardian::factory()->for($school)->create()->id,
        'data' => ['message' => 'Hello'],
    ])],
    'AdmissionSequence' => [fn (School $school): Model => AdmissionSequence::factory()->for($school)->create()],
    'Staff' => [fn (School $school): Model => Staff::factory()->for($school)->create()],
    'TeachingAssignment' => [fn (School $school): Model => TeachingAssignment::factory()->create(['school_id' => $school->id])],
    'ClassTeacherAssignment' => [fn (School $school): Model => ClassTeacherAssignment::factory()->create(['school_id' => $school->id])],
    'ClassSubject' => [fn (School $school): Model => ClassSubject::factory()->state(['school_class_id' => SchoolClass::factory()->for($school)])->create()],
    'Programme' => [fn (School $school): Model => Programme::factory()->for($school)->create()],
    'ProgrammeSubject' => [fn (School $school): Model => ProgrammeSubject::factory()->state(['programme_id' => Programme::factory()->for($school)])->create()],
]);

beforeEach(function () {
    $this->schoolA = School::factory()->create();
    $this->schoolB = School::factory()->create();
    $this->current = app(CurrentSchool::class);
});

it('is invisible from another school', function (Closure $make) {
    $record = $make($this->schoolA);
    $class = $record::class;

    $this->current->runFor($this->schoolB, function () use ($class, $record) {
        expect($class::find($record->getKey()))->toBeNull()
            ->and($class::whereKey($record->getKey())->exists())->toBeFalse()
            ->and($class::count())->toBe(0);

        expect(fn () => $class::findOrFail($record->getKey()))->toThrow(ModelNotFoundException::class);
    });

    $this->current->runFor($this->schoolA, fn () => expect($class::find($record->getKey()))->not->toBeNull());
})->with('school-owned models');

it('cannot be updated or deleted from another school', function (Closure $make) {
    $record = $make($this->schoolA);
    $class = $record::class;
    $before = $record->fresh()->getAttributes();

    // Any timestamp column will do; the append-only ledger has created_at only.
    $column = $record->getUpdatedAtColumn() ?? $record->getCreatedAtColumn();

    $this->current->runFor($this->schoolB, function () use ($class, $record, $column) {
        expect($class::whereKey($record->getKey())->update([$column => now()->addYear()]))->toBe(0)
            ->and($class::whereKey($record->getKey())->delete())->toBe(0);
    });

    expect($record->fresh()?->getAttributes())->toBe($before);
})->with('school-owned models');

it('is stamped with the school it was created in', function (Closure $make) {
    $record = $make($this->schoolA);

    expect($record->getAttribute('school_id'))->toBe($this->schoolA->id);
})->with('school-owned models');
