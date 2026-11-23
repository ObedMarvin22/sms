<?php

use App\Models\Announcement;
use App\Models\Bill;
use App\Models\Guardian;
use App\Models\Payment;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoParentSeeder;
use Database\Seeders\DemoSchoolsSeeder;

/*
| The demo parent: a ready parent-app login on Greenhill with fees, a payment and news.
*/

beforeEach(function () {
    $this->travelTo('2026-10-28 09:00:00'); // a school day in the demo first term
    $this->seed(DatabaseSeeder::class);
    $this->greenhill = School::where('subdomain', 'greenhill')->sole();
});

it('gives a Greenhill guardian a parent login with bills, a receipt and news', function () {
    app(CurrentSchool::class)->runFor($this->greenhill, function () {
        $guardian = Guardian::whereNotNull('user_id')->sole();
        $children = $guardian->links()->pluck('student_id');

        expect(User::find($guardian->user_id)?->phone)->toBe($guardian->phone)
            ->and(Bill::whereIn('student_id', $children)->count())->toBe($children->count())
            ->and(Payment::whereIn('student_id', $children)->count())->toBe(1)
            ->and(Announcement::where('title', 'Welcome to the parent app')->exists())->toBeTrue();
    });
});

it('lets that parent sign in and download a statement', function () {
    $guardian = app(CurrentSchool::class)->runFor($this->greenhill, fn () => Guardian::whereNotNull('user_id')->sole());

    $token = $this->postJson('http://greenhill.localhost/api/v1/auth/login', ['phone' => $guardian->phone, 'password' => DemoSchoolsSeeder::PASSWORD])
        ->assertOk()->json('token');
    $childId = $this->withToken($token)->getJson('http://greenhill.localhost/api/v1/children')->json('data.0.id');

    $this->withToken($token)->get("http://greenhill.localhost/api/v1/children/{$childId}/statement.pdf")->assertOk();
});

it('changes nothing when run again', function () {
    $count = fn () => app(CurrentSchool::class)->runFor($this->greenhill, fn () => [Bill::count(), Payment::count(), Announcement::count(), User::count()]);
    $before = $count();

    $this->seed(DemoParentSeeder::class);

    expect($count())->toBe($before);
});
