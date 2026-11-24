<?php

namespace App\Actions\Results;

use App\Enums\PermissionKey;
use App\Models\ReportCard;
use App\Models\Stream;
use App\Models\Term;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;

/**
 * Shared by the head's approve, publish and withdraw Actions.
 */
final class ReportCardRules
{
    public static function authorizeHead(User $by): void
    {
        if (! $by->can(PermissionKey::GradesApprove->value)) {
            throw new AuthorizationException('Only the head can approve and publish report cards.');
        }
    }

    /**
     * @return Collection<int, ReportCard>
     */
    public static function cards(Term $term, Stream $stream): Collection
    {
        return ReportCard::where(['term_id' => $term->id, 'stream_id' => $stream->id])->get();
    }
}
