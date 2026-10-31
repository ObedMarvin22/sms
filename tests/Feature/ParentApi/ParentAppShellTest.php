<?php

use App\Enums\PaystackTransactionStatus;
use App\Models\PaystackTransaction;
use App\Models\School;
use App\Support\Branding\SchoolIcon;
use Illuminate\Support\Facades\Http;

/*
| The parent app's shell (/parent) and the files that make it installable. The screens
| themselves are Vue (resources/js/parent) and talk only to /api/v1.
*/

beforeEach(function () {
    $this->greenhill = School::factory()->active()->create(['subdomain' => 'greenhill', 'name' => 'Greenhill Academy', 'primary_colour' => '#166534']);
    $this->hillview = School::factory()->active()->create(['subdomain' => 'hillview', 'name' => 'Hillview School', 'primary_colour' => '#1d4ed8']);
});

it("shows the school's name and colour, the Vue mount point and the manifest", function () {
    $this->get('http://greenhill.localhost/parent')
        ->assertOk()
        ->assertSee('<title>Greenhill Academy</title>', false)
        ->assertSee('id="app"', false)
        ->assertSee('data-school-name="Greenhill Academy"', false)
        ->assertSee('--school: #166534', false)
        ->assertSee('<link rel="manifest" href="/parent/manifest.webmanifest">', false)
        ->assertDontSee('Hillview');
});

it('passes no private data to the page', function () {
    $this->greenhill->forceFill(['paystack_subaccount_code' => 'ACCT_secret', 'sms_sender_id' => 'GREENHILL'])->save();

    $this->get('http://greenhill.localhost/parent')
        ->assertDontSee('ACCT_secret')
        ->assertDontSee('GREENHILL');
});

it('is 404 for an unknown school', function () {
    $this->get('http://nowhere.localhost/parent')->assertNotFound();
});

it('gives each school its own manifest', function () {
    $manifest = $this->get('http://greenhill.localhost/parent/manifest.webmanifest')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json');

    expect($manifest->json())->toMatchArray([
        'name' => 'Greenhill Academy',
        'start_url' => '/parent',
        'scope' => '/parent',
        'display' => 'standalone',
        'theme_color' => '#166534',
    ])->and(array_column($manifest->json('icons'), 'sizes'))->toBe(['192x192', '512x512'])
        ->and($manifest->getContent())->not->toContain('Hillview');

    expect($this->get('http://hillview.localhost/parent/manifest.webmanifest')->json('name'))->toBe('Hillview School');
});

it('draws the icon in both sizes', function (int $size) {
    $response = $this->get("http://greenhill.localhost/parent/icon-{$size}.png")
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    $info = getimagesizefromstring($response->getContent());
    expect([$info[0], $info[1]])->toBe([$size, $size]);
})->with(SchoolIcon::SIZES);

it('refuses other icon sizes', function () {
    $this->get('http://greenhill.localhost/parent/icon-64.png')->assertNotFound();
});

it('uses up to two initials and falls back to Orange Tree orange', function () {
    $school = School::factory()->make(['name' => 'St. Mary\'s Senior High School', 'primary_colour' => 'not-a-colour']);

    expect(SchoolIcon::initials($school))->toBe('SM')
        ->and(SchoolIcon::colour($school))->toBe(SchoolIcon::DEFAULT_COLOUR);
});

it('serves the service worker for the /parent scope with a build version', function () {
    $response = $this->get('http://greenhill.localhost/parent/sw.js')
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-cache, private')
        ->assertHeader('Service-Worker-Allowed', '/parent');

    expect($response->headers->get('Content-Type'))->toStartWith('application/javascript')
        ->and($response->getContent())->toStartWith("const VERSION = '")
        ->and($response->getContent())->toContain("url.pathname.startsWith('/api/')"); // API answers are never cached
});

it('links the payment return page back to the child in the parent app', function () {
    Http::preventStrayRequests();
    $transaction = PaystackTransaction::factory()->create(['school_id' => $this->greenhill->id]);
    // A finished attempt: the page does not ask Paystack again.
    PaystackTransaction::withoutGlobalScopes()->whereKey($transaction->id)->update(['status' => PaystackTransactionStatus::Failed->value]);

    $this->get("http://greenhill.localhost/pay/return?reference={$transaction->reference}")
        ->assertOk()
        ->assertSee('href="http://greenhill.localhost/parent#/children/'.$transaction->student_id.'"', false);
});
