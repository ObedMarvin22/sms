<?php

namespace App\Livewire\School;

use App\Actions\Attendance\AttendanceRules;
use App\Actions\Attendance\SetAttendanceLockTime;
use App\Actions\Fees\SetOffersBoarding;
use App\Actions\Schools\UpdateSchoolProfile;
use App\Actions\Students\SetAdmissionFormat;
use App\Enums\PermissionKey;
use App\Enums\SchoolLevel;
use App\Models\School;
use App\Support\Settings\SchoolSettings;
use App\Support\Students\AdmissionNumberFormat;
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

    public string $admission_prefix = '';

    public string $admission_number_format = '';

    public string $attendance_lock_time = '';

    public bool $offers_boarding = false;

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
        $format = AdmissionNumberFormat::for($school);
        $this->admission_prefix = $format->prefix;
        $this->admission_number_format = $format->format;
        $this->attendance_lock_time = AttendanceRules::lockTime($school);
        $this->offers_boarding = (bool) SchoolSettings::for($school)->get('offers_boarding', false);
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

    public function saveAdmissionFormat(SetAdmissionFormat $setAdmissionFormat): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);
        $this->resetErrorBag();

        try {
            $setAdmissionFormat->handle($this->school(), $this->admission_prefix, $this->admission_number_format);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        Flux::toast(variant: 'success', text: __('Admission number format saved.'));
    }

    public function updatedOffersBoarding(SetOffersBoarding $setOffersBoarding): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);
        $this->resetErrorBag();

        try {
            $setOffersBoarding->handle($this->school(), $this->offers_boarding);
        } catch (ValidationException $e) {
            $this->offers_boarding = ! $this->offers_boarding;
            $this->setErrorBag($e->validator->errors());

            return;
        }

        Flux::toast(variant: 'success', text: $this->offers_boarding ? __('Boarding turned on.') : __('Boarding turned off.'));
    }

    public function saveAttendanceLockTime(SetAttendanceLockTime $setLockTime): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);
        $this->resetErrorBag();

        try {
            $setLockTime->handle($this->school(), $this->attendance_lock_time);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        Flux::toast(variant: 'success', text: __('Registers now lock at :time.', ['time' => $this->attendance_lock_time]));
    }

    /**
     * Example of the next number in the format being edited (or null while it is invalid).
     */
    public function admissionPreview(): ?string
    {
        if (AdmissionNumberFormat::problem($this->admission_number_format) !== null) {
            return null;
        }

        return (new AdmissionNumberFormat($this->admission_number_format, mb_strtoupper(trim($this->admission_prefix))))->render((int) now()->year, 1);
    }

    public function school(): School
    {
        return app(CurrentSchool::class)->require();
    }
}
