<?php

namespace App\Actions\Fees;

use App\Enums\Residence;
use App\Models\School;
use App\Models\Student;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Validation\ValidationException;

/**
 * Whether the school takes boarders. On: boarding fee structures and the residence switch
 * appear. Off is refused while any student is still a boarder.
 */
class SetOffersBoarding
{
    public function __construct(private CurrentSchool $currentSchool) {}

    public function handle(School $school, bool $offers): School
    {
        if (! $offers) {
            $boarders = $this->currentSchool->runFor($school, fn () => Student::where('residence', Residence::Boarding->value)->count());
            if ($boarders > 0) {
                throw ValidationException::withMessages(['offers_boarding' => trans_choice(
                    '{1} :count student is a boarder. Make them a day student first.|[2,*] :count students are boarders. Make them day students first.',
                    $boarders, ['count' => $boarders],
                )]);
            }
        }

        $school->settings = [...($school->settings ?? []), 'offers_boarding' => $offers];
        $school->save();

        activity('schools')->performedOn($school)->event('offers_boarding_set')
            ->withProperties(['offers_boarding' => $offers])->log($offers ? 'boarding turned on' : 'boarding turned off');

        return $school;
    }
}
