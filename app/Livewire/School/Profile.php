<?php

namespace App\Livewire\School;

use App\Actions\Schools\UpdateSchoolProfile;
use App\Enums\PermissionKey;
use App\Enums\SchoolLevel;
use App\Models\School;
use App\Support\Tenancy\CurrentSchool;
use Flux\Flux;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Title('School profile')]
class Profile extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $subdomain = '';

    public ?string $primary_colour = null;

    public ?string $secondary_colour = null;

    public ?string $phone = null;

    public ?string $email = null;

    public ?string $address = null;

    /** @var list<string> */
    public array $levels = [];

    public ?TemporaryUploadedFile $logo = null;

    public function mount(): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);

        $school = $this->school();
        $this->name = $school->name;
        $this->subdomain = $school->subdomain;
        $this->primary_colour = $school->primary_colour;
        $this->secondary_colour = $school->secondary_colour;
        $this->phone = $school->phone;
        $this->email = $school->email;
        $this->address = $school->address;
        $this->levels = array_values($school->levels_offered->map(fn (SchoolLevel $level) => $level->value)->all());
    }

    public function save(UpdateSchoolProfile $updateSchoolProfile): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);
        $this->resetErrorBag();

        try {
            $school = $updateSchoolProfile->handle($this->school(), [
                'name' => $this->name,
                'primary_colour' => $this->primary_colour,
                'secondary_colour' => $this->secondary_colour,
                'phone' => $this->phone,
                'email' => $this->email,
                'address' => $this->address,
                'levels' => $this->levels,
            ], $this->logo);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError(str_starts_with($field, 'levels') ? 'levels' : $field, $messages[0]);
            }

            return;
        }

        $this->phone = $school->phone;
        $this->logo = null;

        Flux::toast(variant: 'success', text: __('School profile saved.'));
    }

    /**
     * @return list<SchoolLevel>
     */
    public function levelOptions(): array
    {
        return SchoolLevel::cases();
    }

    public function school(): School
    {
        return app(CurrentSchool::class)->require();
    }
}
