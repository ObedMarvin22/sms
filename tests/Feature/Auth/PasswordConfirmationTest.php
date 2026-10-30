<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\OnSchoolSubdomain;
use Tests\TestCase;

class PasswordConfirmationTest extends TestCase
{
    use OnSchoolSubdomain, RefreshDatabase;

    public function test_confirm_password_screen_can_be_rendered(): void
    {
        $user = User::factory()->for($this->school)->create();

        $response = $this->actingAs($user)->get(route('password.confirm'));

        $response->assertOk();
    }
}
