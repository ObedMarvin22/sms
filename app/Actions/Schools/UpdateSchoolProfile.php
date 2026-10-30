<?php

namespace App\Actions\Schools;

use App\Enums\SchoolLevel;
use App\Models\School;
use App\Support\Phone;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Updates a school's profile: name, colours, contacts, levels and (optionally) its logo.
 *
 * Callers must already have checked settings.manage. The subdomain is never changed here.
 */
class UpdateSchoolProfile
{
    public const LOGO_DISK = 'local';

    /** Kilobytes. */
    public const LOGO_MAX_KB = 1024;

    /**
     * @param  array{name: string, primary_colour?: ?string, secondary_colour?: ?string, phone?: ?string, email?: ?string, address?: ?string, levels: list<string>}  $input
     *
     * @throws ValidationException
     */
    public function handle(School $school, array $input, ?UploadedFile $logo = null): School
    {
        $hex = ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];

        // Tidy first (people paste addresses with spaces), then validate the tidy values.
        $input = array_map(fn (mixed $value) => is_string($value) ? trim($value) : $value, $input);
        if (isset($input['email'])) {
            $input['email'] = strtolower($input['email']);
        }

        $data = Validator::make(
            [...$input, 'logo' => $logo],
            [
                'name' => ['required', 'string', 'max:255'],
                'primary_colour' => $hex,
                'secondary_colour' => $hex,
                'phone' => ['nullable', 'string', 'max:30', function (string $attribute, mixed $value, \Closure $fail): void {
                    if (Phone::normalise((string) $value) === null) {
                        $fail('Enter a Ghanaian phone number, e.g. 024 412 3456.');
                    }
                }],
                'email' => ['nullable', 'email', 'max:255'],
                'address' => ['nullable', 'string', 'max:500'],
                'levels' => ['required', 'array', 'min:1'],
                'levels.*' => ['distinct', Rule::enum(SchoolLevel::class)],
                // No SVG: it can carry scripts. Raster images only.
                'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:'.self::LOGO_MAX_KB],
            ],
            ['primary_colour.regex' => 'Use a colour like #0A7D3C.', 'secondary_colour.regex' => 'Use a colour like #0A7D3C.'],
        )->validate();

        $school->fill([
            'name' => trim($data['name']),
            'primary_colour' => self::blankToNull($data['primary_colour'] ?? null),
            'secondary_colour' => self::blankToNull($data['secondary_colour'] ?? null),
            'phone' => Phone::normalise(self::blankToNull($data['phone'] ?? null)),
            'email' => self::blankToNull($data['email'] ?? null),
            'address' => self::blankToNull($data['address'] ?? null),
            'levels_offered' => array_map(SchoolLevel::from(...), $data['levels']),
        ]);

        if ($logo !== null) {
            $previous = $school->logo_path;
            $extension = strtolower($logo->guessExtension() ?? $logo->getClientOriginalExtension());
            $path = $logo->storeAs("schools/{$school->id}/branding", "logo.{$extension}", self::LOGO_DISK);

            if ($path === false) {
                throw ValidationException::withMessages(['logo' => 'The logo could not be saved. Please try again.']);
            }

            $school->logo_path = $path;

            if ($previous !== null && $previous !== $path) {
                Storage::disk(self::LOGO_DISK)->delete($previous);
            }
        }

        $school->save();

        return $school;
    }

    private static function blankToNull(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
