<?php

namespace App\Actions\Platform;

use App\Enums\PlatformRoleKey;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Creates an Orange Tree staff account for the console (school_id null).
 */
class CreatePlatformUser
{
    /**
     * @throws ValidationException
     */
    public function handle(string $name, string $email, string $password, PlatformRoleKey $role): User
    {
        $email = strtolower(trim($email));

        Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                // The (school_id, email) index allows repeated emails when school_id is null.
                'email' => ['required', 'email', 'max:255', Rule::unique('users')->whereNull('school_id')],
                'password' => ['required', Password::min(12)->mixedCase()->numbers()->symbols()],
            ],
        )->validate();

        $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);
        $user->school_id = null;
        $user->platform_role = $role;
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }
}
