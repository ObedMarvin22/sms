<?php

namespace App\Console\Commands;

use App\Actions\Platform\CreatePlatformUser;
use App\Enums\PlatformRoleKey;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

#[Signature('platform:create-admin')]
#[Description('Create an Orange Tree console user (prompts for name, email, password and role)')]
class CreatePlatformAdmin extends Command
{
    public function handle(CreatePlatformUser $createPlatformUser): int
    {
        $name = text('Name', required: true);
        $email = text('Email', required: true);
        $password = password('Password (min 12, mixed case, number, symbol)', required: true);
        $role = PlatformRoleKey::from(select(
            'Role',
            collect(PlatformRoleKey::cases())->mapWithKeys(fn (PlatformRoleKey $r) => [$r->value => $r->label()])->all(),
            default: PlatformRoleKey::SuperAdmin->value,
        ));

        try {
            $user = $createPlatformUser->handle($name, $email, $password, $role);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->components->error($message);
                }
            }

            return self::FAILURE;
        }

        $this->components->info("Created {$role->label()} {$user->email}. Log in at the console (admin subdomain).");

        return self::SUCCESS;
    }
}
