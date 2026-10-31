<?php

require_once __DIR__.'/helpers.php';

use App\Enums\GuardianAccess;
use App\Enums\PermissionKey;
use App\Enums\RoleKey;
use App\Enums\SmsEvent;
use App\Livewire\Fees\Messages;
use App\Models\GuardianStudent;
use App\Models\MessageCreditEntry;
use App\Models\ParentOtp;
use App\Models\School;
use App\Models\SmsMessage;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    setUpParentSchool();
});

describe('asking for a code', function () {
    it('texts a code to a registered guardian and charges the school', function () {
        guardianWithChild();

        $this->postJson(api('auth/otp'), ['phone' => '024 400 0111'])
            ->assertAccepted()
            ->assertJson(['message' => 'If this number is registered with the school, a code is on its way by SMS.']);

        expect($this->sms->lastCodeTo('+233244000111'))->toMatch('/^\d{6}$/')
            ->and($this->sms->sent[0]['message'])->toContain('parent app code')
            ->and(MessageCreditEntry::balance())->toBe(49)
            ->and(ParentOtp::sole()->phone)->toBe('+233244000111');
    });

    it('sends codes even when the school has no SMS notices turned on', function () {
        guardianWithChild();
        expect($this->school->settings['sms_events'] ?? [])->toBe([]);

        $this->postJson(api('auth/otp'), ['phone' => '0244000111'])->assertAccepted();

        expect($this->sms->sent)->toHaveCount(1);
    });

    it('answers the same for a number that is not registered, and sends nothing', function () {
        $this->postJson(api('auth/otp'), ['phone' => '0209999999'])
            ->assertAccepted()
            ->assertJson(['message' => 'If this number is registered with the school, a code is on its way by SMS.']);

        expect($this->sms->sent)->toBe([])
            ->and(ParentOtp::count())->toBe(0)
            ->and(MessageCreditEntry::balance())->toBe(50);
    });

    it('sends nothing to a guardian who is contact only', function () {
        guardianWithChild(access: GuardianAccess::None);

        $this->postJson(api('auth/otp'), ['phone' => '0244000111'])->assertAccepted();

        expect($this->sms->sent)->toBe([]);
    });

    it('refuses a number that is not a Ghana phone', function () {
        $this->postJson(api('auth/otp'), ['phone' => '12345'])->assertJsonValidationErrors('phone');
    });

    it('makes every number wait a minute before another code, registered or not', function (string $phone) {
        guardianWithChild();

        $this->postJson(api('auth/otp'), ['phone' => $phone])->assertAccepted();
        $this->postJson(api('auth/otp'), ['phone' => $phone])
            ->assertJsonValidationErrors(['phone' => 'Please wait']);

        $this->travel(61)->seconds();
        $this->postJson(api('auth/otp'), ['phone' => $phone])->assertAccepted();
    })->with(['registered' => '0244000111', 'unknown' => '0209999999']);

    it('allows three codes per number in ten minutes, however it is typed', function () {
        guardianWithChild();

        foreach (['0244000111', '+233 24 400 0111', '233244000111'] as $phone) {
            $this->postJson(api('auth/otp'), ['phone' => $phone])->assertAccepted();
            $this->travel(61)->seconds();
        }

        $this->postJson(api('auth/otp'), ['phone' => '244000111'])->assertTooManyRequests();
        expect($this->sms->sent)->toHaveCount(3);
    });

    it('tells everyone when the school has no credit, registered or not', function (string $phone) {
        guardianWithChild();
        MessageCreditEntry::factory()->for($this->school)->create(['units' => -50, 'type' => 'message']);

        $this->postJson(api('auth/otp'), ['phone' => $phone])
            ->assertJsonValidationErrors(['phone' => "Your school can't send codes right now."]);

        expect($this->sms->sent)->toBe([]);
    })->with(['registered' => '0244000111', 'unknown' => '0209999999']);

    it('keeps the code off staff screens and out of the stored message', function () {
        guardianWithChild();
        $this->postJson(api('auth/otp'), ['phone' => '0244000111'])->assertAccepted();
        $code = $this->sms->lastCodeTo('+233244000111');

        $sms = SmsMessage::sole();
        expect($sms->event)->toBe(SmsEvent::Otp->value)
            ->and($sms->message)->not->toContain($code)
            ->and($sms->message)->toContain('******');

        $this->actingAs(staffWith(RoleKey::Bursar));
        Livewire::test(Messages::class)->assertDontSee($code)->assertSee('******');
    });

    it('hides a code still waiting to go', function () {
        $sms = SmsMessage::factory()->for($this->school)->create(['event' => SmsEvent::Otp->value, 'message' => 'Your code is 482913.']);

        expect($sms->displayMessage())->toBe('Your code is ******.');
    });

    it('does not offer codes as a notice schools can switch', function () {
        expect(SmsEvent::optional())->not->toContain(SmsEvent::Otp)
            ->and(SmsEvent::tryOptional('otp'))->toBeNull();
    });
});

describe('activating with the code', function () {
    it('creates the parent login, links it to the guardian and returns a token', function () {
        $guardian = guardianWithChild();

        $token = activateParent();

        $user = User::where('phone', '+233244000111')->sole();
        expect($user->school_id)->toBe($this->school->id)
            ->and($user->email)->toBeNull()
            ->and($user->name)->toBe('Akosua Mensah')
            ->and($user->hasRole(RoleKey::Parent->value))->toBeTrue()
            ->and($guardian->fresh()->user_id)->toBe($user->id)
            ->and(ParentOtp::sole()->used_at)->not->toBeNull();

        $this->withToken($token)->getJson(api('me'))
            ->assertOk()
            ->assertJson(['data' => ['name' => 'Akosua Mensah', 'phone' => '+233244000111', 'school' => ['subdomain' => 'greenhill']]]);
    });

    it('logs the new login and the guardian link', function () {
        $guardian = guardianWithChild();
        activateParent();

        expect(Activity::where('log_name', 'users')->where('event', 'created')->exists())->toBeTrue()
            ->and(Activity::where('log_name', 'guardians')->where('subject_id', $guardian->id)->where('event', 'updated')->exists())->toBeTrue();
    });

    it('refuses a wrong code and counts the try', function () {
        guardianWithChild();
        $this->postJson(api('auth/otp'), ['phone' => '0244000111']);

        $this->postJson(api('auth/set-password'), ['phone' => '0244000111', 'code' => '000000', 'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1'])
            ->assertJsonValidationErrors(['code' => 'That code is wrong or has expired.']);

        expect(ParentOtp::sole()->attempts)->toBe(1)
            ->and(User::where('phone', '+233244000111')->exists())->toBeFalse();
    });

    it('stops accepting a code after five wrong tries', function () {
        guardianWithChild();
        $this->postJson(api('auth/otp'), ['phone' => '0244000111']);
        $code = $this->sms->lastCodeTo('+233244000111');
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $this->postJson(api('auth/set-password'), ['phone' => '0244000111', 'code' => $wrong, 'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1']);
            $this->travel(13)->seconds(); // stay under the per-minute limit
        }

        $this->postJson(api('auth/set-password'), ['phone' => '0244000111', 'code' => $code, 'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1'])
            ->assertJsonValidationErrors('code');
    });

    it('refuses a code after ten minutes', function () {
        guardianWithChild();
        $this->postJson(api('auth/otp'), ['phone' => '0244000111']);
        $this->travel(11)->minutes();

        $this->postJson(api('auth/set-password'), ['phone' => '0244000111', 'code' => $this->sms->lastCodeTo('+233244000111'), 'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1'])
            ->assertJsonValidationErrors('code');
    });

    it('only accepts the newest code, and each code once', function () {
        guardianWithChild();
        $this->postJson(api('auth/otp'), ['phone' => '0244000111']);
        $first = $this->sms->lastCodeTo('+233244000111');
        $this->travel(61)->seconds();
        $this->postJson(api('auth/otp'), ['phone' => '0244000111']);
        $second = $this->sms->lastCodeTo('+233244000111');

        $body = ['phone' => '0244000111', 'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1'];
        if ($first !== $second) {
            $this->postJson(api('auth/set-password'), [...$body, 'code' => $first])->assertJsonValidationErrors('code');
        }
        $this->postJson(api('auth/set-password'), [...$body, 'code' => $second])->assertOk();
        $this->postJson(api('auth/set-password'), [...$body, 'code' => $second])->assertJsonValidationErrors('code');
    });

    it('needs the password typed twice', function () {
        guardianWithChild();
        $this->postJson(api('auth/otp'), ['phone' => '0244000111']);

        $this->postJson(api('auth/set-password'), ['phone' => '0244000111', 'code' => $this->sms->lastCodeTo('+233244000111'), 'password' => 'secret-pass-1', 'password_confirmation' => 'other'])
            ->assertJsonValidationErrors('password');
    });

    it('sets a new password later and signs out every other device', function () {
        guardianWithChild();
        $old = activateParent(password: 'first-pass-1');
        $this->travel(61)->seconds();

        $new = activateParent(password: 'second-pass-2');

        expect(User::where('phone', '+233244000111')->count())->toBe(1);
        $this->app['auth']->forgetGuards();
        $this->withToken($old)->getJson(api('me'))->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($new)->getJson(api('me'))->assertOk();
        $this->postJson(api('auth/login'), ['phone' => '0244000111', 'password' => 'second-pass-2'])->assertOk();
    });
});

describe('signing in', function () {
    beforeEach(function () {
        $this->guardian = guardianWithChild();
        activateParent(password: 'secret-pass-1');
    });

    it('signs in with phone and password, however the phone is typed', function () {
        $token = $this->postJson(api('auth/login'), ['phone' => '+233 24 400 0111', 'password' => 'secret-pass-1', 'device_name' => 'Tecno Spark'])
            ->assertOk()->json('token');

        expect(PersonalAccessToken::findToken($token)->name)->toBe('Tecno Spark');
        $this->withToken($token)->getJson(api('me'))->assertOk();
    });

    it('gives the same answer for a wrong password and an unknown phone', function () {
        $this->postJson(api('auth/login'), ['phone' => '0244000111', 'password' => 'nope'])
            ->assertJsonValidationErrors(['phone' => 'The phone number or password is wrong.']);
        $this->postJson(api('auth/login'), ['phone' => '0209999999', 'password' => 'secret-pass-1'])
            ->assertJsonValidationErrors(['phone' => 'The phone number or password is wrong.']);
    });

    it('refuses a parent whose children are all set to contact only', function () {
        GuardianStudent::where('guardian_id', $this->guardian->id)->update(['access_level' => GuardianAccess::None->value]);

        $this->postJson(api('auth/login'), ['phone' => '0244000111', 'password' => 'secret-pass-1'])
            ->assertJsonValidationErrors(['phone' => 'Your school has not given this number access to the parent app.']);
    });

    it('allows five tries a minute', function () {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson(api('auth/login'), ['phone' => '0244000111', 'password' => 'nope'])->assertUnprocessable();
        }

        $this->postJson(api('auth/login'), ['phone' => '0244000111', 'password' => 'secret-pass-1'])->assertTooManyRequests();
    });

    it('cannot sign in on another school', function () {
        School::factory()->active()->create(['subdomain' => 'hillview']);

        $this->postJson('http://hillview.localhost/api/v1/auth/login', ['phone' => '0244000111', 'password' => 'secret-pass-1'])
            ->assertJsonValidationErrors('phone');
    });
});

describe('tokens', function () {
    beforeEach(function () {
        guardianWithChild();
        $this->token = activateParent();
    });

    it('ends after 90 days', function () {
        $this->travel(89)->days();
        $this->withToken($this->token)->getJson(api('me'))->assertOk();

        $this->app['auth']->forgetGuards();
        $this->travel(2)->days();
        $this->withToken($this->token)->getJson(api('me'))->assertUnauthorized();
    });

    it('ends only this device on sign out', function () {
        $other = $this->postJson(api('auth/login'), ['phone' => '0244000111', 'password' => 'secret-pass-1'])->json('token');

        $this->withToken($this->token)->postJson(api('auth/logout'))->assertNoContent();

        $this->app['auth']->forgetGuards();
        $this->withToken($this->token)->getJson(api('me'))->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($other)->getJson(api('me'))->assertOk();
    });

    it("is refused on another school's subdomain", function () {
        School::factory()->active()->create(['subdomain' => 'hillview']);

        $this->withToken($this->token)->getJson('http://hillview.localhost/api/v1/me')->assertForbidden();
    });

    it('is refused once the login is switched off', function () {
        User::where('phone', '+233244000111')->update(['deactivated_at' => now()]);

        $this->withToken($this->token)->getJson(api('me'))->assertUnauthorized();
    });

    it('refuses a staff token on parent routes', function () {
        $staff = staffWith(RoleKey::Bursar)->createToken('staff')->plainTextToken;

        $this->app['auth']->forgetGuards();
        $this->withToken($staff)->getJson(api('me'))->assertForbidden();
    });

    it("refuses a parent's token that the parent app did not issue", function () {
        $other = User::where('phone', '+233244000111')->sole()->createToken('script', ['reports'])->plainTextToken;

        $this->app['auth']->forgetGuards();
        $this->withToken($other)->getJson(api('me'))->assertForbidden();
    });

    it('is refused when the school takes the parent app permission off the Parent role', function () {
        Role::findByName(RoleKey::Parent->value)->revokePermissionTo(PermissionKey::PortalChildren->value);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->withToken($this->token)->getJson(api('me'))->assertForbidden();
    });

    it('refuses parent routes without a token', function () {
        $this->getJson(api('me'))->assertUnauthorized();
    });
});

describe('staff screens', function () {
    it('refuses a parent-only user at the staff login', function () {
        guardianWithChild();
        activateParent(password: 'secret-pass-1');
        // Even if a parent login somehow had an email, the staff login stays shut.
        User::where('phone', '+233244000111')->update(['email' => 'akosua@example.com']);

        $this->post('http://greenhill.localhost/login', ['email' => 'akosua@example.com', 'password' => 'secret-pass-1'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    });

    it('still lets a staff member who is also a parent sign in', function () {
        // No two-factor (a class teacher needs none), so the login completes in one step.
        $teacher = User::factory()->for($this->school)->create();
        $teacher->assignRole([RoleKey::ClassTeacher->value, RoleKey::Parent->value]);

        $this->post('http://greenhill.localhost/login', ['email' => $teacher->email, 'password' => 'password'])
            ->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($teacher);
    });
});
