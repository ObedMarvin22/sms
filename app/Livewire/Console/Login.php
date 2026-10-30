<?php

namespace App\Livewire\Console;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Console login for Orange Tree staff. The "platform" guard's provider only finds
 * platform users here, because the admin subdomain has no current school.
 */
#[Layout('layouts::auth')]
#[Title('Console log in')]
class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public function login(): void
    {
        $this->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);

        $key = 'console-login|'.Str::lower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key), 'minutes' => 1]),
            ]);
        }

        if (! Auth::guard('platform')->attempt(['email' => $this->email, 'password' => $this->password])) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($key);
        session()->regenerate();

        $this->redirectRoute('console.schools.index', navigate: true);
    }
}
