<?php

namespace App\Actions\ParentApp;

use App\Enums\GuardianAccess;
use App\Models\Guardian;
use App\Models\GuardianStudent;
use App\Models\Student;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * The only way the parent API reaches a child: through the guardian's own link. A child the
 * guardian is not linked to (or is contact only for) is "not found", so the API never confirms
 * that another family's child exists. Fees need full access.
 */
class FindParentChild
{
    public function handle(Guardian $guardian, int $studentId, bool $fees = false): GuardianStudent
    {
        $link = self::visibleLinks($guardian)->with('student')->where('student_id', $studentId)->first();

        if ($link === null) {
            throw (new ModelNotFoundException)->setModel(Student::class, [$studentId]);
        }

        if ($fees && $link->access_level !== GuardianAccess::Full) {
            throw new AuthorizationException('Your access to this child does not include fees.');
        }

        return $link;
    }

    /**
     * The guardian's links the parent app may show: above "contact only", to a child on file.
     *
     * @return Builder<GuardianStudent>
     */
    public static function visibleLinks(Guardian $guardian): Builder
    {
        return GuardianStudent::where('guardian_id', $guardian->id)
            ->where('access_level', '!=', GuardianAccess::None->value)
            ->whereIn('student_id', Student::select('id')); // not soft-deleted
    }
}
