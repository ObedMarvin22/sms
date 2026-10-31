<?php

/*
| Helpers shared by the parent API test files (loaded with require_once, so declared once).
*/

require_once __DIR__.'/../Fees/helpers.php';

use App\Enums\GuardianAccess;
use App\Models\Guardian;
use App\Models\GuardianStudent;
use App\Models\MessageCreditEntry;
use App\Models\Student;
use App\Services\Sms\SmsGateway;
use App\Support\Tenancy\CurrentSchool;
use Tests\Fakes\FakeSmsGateway;

/**
 * The fee school (Greenhill) with SMS credit and a fake gateway on $this->sms.
 */
function setUpParentSchool(int $credit = 50): void
{
    setUpFeeSchool();
    $test = test();
    $test->sms = new FakeSmsGateway;
    app()->instance(SmsGateway::class, $test->sms);
    if ($credit > 0) {
        MessageCreditEntry::factory()->for(app(CurrentSchool::class)->require())->create(['units' => $credit]);
    }
}

/**
 * A guardian with one child at the given access level.
 */
function guardianWithChild(string $phone = '+233244000111', GuardianAccess $access = GuardianAccess::Full, ?Student $child = null): Guardian
{
    $school = app(CurrentSchool::class)->require();
    $guardian = Guardian::where('phone', $phone)->first()
        ?? Guardian::factory()->for($school)->create(['phone' => $phone, 'name' => 'Akosua Mensah']);

    $link = new GuardianStudent([
        'guardian_id' => $guardian->id,
        'student_id' => ($child ?? feeStudent())->id,
        'relationship' => 'mother',
        'access_level' => $access->value,
    ]);
    $link->save();

    return $guardian;
}

function api(string $path): string
{
    return 'http://greenhill.localhost/api/v1/'.ltrim($path, '/');
}

/**
 * Activates the guardian's parent app and returns the token.
 */
function activateParent(string $phone = '+233244000111', string $password = 'secret-pass-1'): string
{
    $test = test();
    $test->postJson(api('auth/otp'), ['phone' => $phone])->assertAccepted();
    $test->travel(61)->seconds();

    return $test->postJson(api('auth/set-password'), [
        'phone' => $phone,
        'code' => $test->sms->lastCodeTo($phone),
        'password' => $password,
        'password_confirmation' => $password,
    ])->assertOk()->json('token');
}
