<?php

namespace App\Livewire\Console\Schools;

use App\Actions\Schools\CreateSchool;
use App\Enums\SchoolLevel;
use Flux\Flux;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::console')]
#[Title('Create school')]
class Create extends Component
{
    public string $name = '';

    public string $subdomain = '';

    /** @var list<string> */
    public array $levels = [];

    public string $adminName = '';

    public string $adminEmail = '';

    public function mount(): void
    {
        $this->authorize('create-schools');
    }

    public function save(CreateSchool $createSchool): void
    {
        $this->authorize('create-schools');

        // CreateSchool validates; its errors map to these fields.
        $this->resetErrorBag();

        try {
            $school = $createSchool->handle($this->name, $this->subdomain, $this->levels, $this->adminName, $this->adminEmail);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError(match (true) {
                    $field === 'admin_name' => 'adminName',
                    $field === 'admin_email' => 'adminEmail',
                    str_starts_with($field, 'levels') => 'levels',
                    default => $field,
                }, $messages[0]);
            }

            return;
        }

        Flux::toast(variant: 'success', text: __(':name created. An invitation was sent to :email.', [
            'name' => $school->name,
            'email' => $this->adminEmail,
        ]));

        $this->redirectRoute('console.schools.index', navigate: true);
    }

    /**
     * @return list<SchoolLevel>
     */
    public function levelOptions(): array
    {
        return SchoolLevel::cases();
    }
}
