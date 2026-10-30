<?php

namespace App\Actions\Students;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;

/**
 * The student list's search and filters, as a query (the screen paginates it).
 *
 * - Each typed word must match a name or the admission number: "ama mensah" finds Ama Mensah.
 * - A typed phone (3+ digits, nothing else) also finds students by any guardian's phone.
 * - Filters: stream (current enrolment), status ('' = all), gender.
 */
class SearchStudents
{
    /**
     * @param  array{search?: string, stream_id?: int|string|null, status?: string|null, gender?: string|null}  $filters
     * @return Builder<Student>
     */
    public function handle(array $filters): Builder
    {
        $term = trim((string) ($filters['search'] ?? ''));
        $words = $term === '' ? [] : (preg_split('/\s+/', $term) ?: []);
        // Phone-shaped input only, so digits inside admission numbers ("GH/2026/0012") don't count.
        // Leading 0 dropped: "0244 12" finds +233 24412…
        $digits = preg_match('/^[\d\s+()-]+$/', $term) === 1 ? ltrim((string) preg_replace('/\D/', '', $term), '0') : '';
        if (str_starts_with($digits, '233')) {
            $digits = substr($digits, 3);
        }

        $streamId = (int) ($filters['stream_id'] ?? 0);
        $status = StudentStatus::tryFrom((string) ($filters['status'] ?? ''));
        $gender = Gender::tryFrom((string) ($filters['gender'] ?? ''));

        return Student::query()
            ->when($words !== [], fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where(function (Builder $q) use ($words): void {
                    foreach ($words as $word) {
                        $q->where(fn (Builder $q) => $q
                            ->where('admission_number', 'like', "%{$word}%")
                            ->orWhere('first_name', 'like', "%{$word}%")
                            ->orWhere('last_name', 'like', "%{$word}%")
                            ->orWhere('other_names', 'like', "%{$word}%"));
                    }
                })
                ->when(strlen($digits) >= 3, fn (Builder $q) => $q->orWhereHas(
                    'guardianLinks.guardian',
                    fn (Builder $q) => $q->where('phone', 'like', "%{$digits}%")->orWhere('alt_phone', 'like', "%{$digits}%"),
                ))))
            ->when($streamId > 0, fn (Builder $q) => $q->whereHas('currentEnrolment', fn (Builder $q) => $q->where('stream_id', $streamId)))
            ->when($status !== null, fn (Builder $q) => $q->where('status', $status->value))
            ->when($gender !== null, fn (Builder $q) => $q->where('gender', $gender->value))
            ->orderBy('last_name')->orderBy('first_name');
    }
}
