<?php

namespace App\Support\Tenancy;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Finds users only within the current school.
 *
 * Every auth lookup (login, session, remember-me, password reset) goes through
 * newModelQuery(), so all of them are scoped here. With no current school
 * (console, admin domain) only platform users (school_id null) are found.
 */
class SchoolUserProvider extends EloquentUserProvider
{
    /**
     * @template TModel of Model
     *
     * @param  TModel|null  $model
     * @return Builder<TModel>
     */
    protected function newModelQuery($model = null)
    {
        $query = parent::newModelQuery($model);
        $column = $query->getModel()->qualifyColumn('school_id');

        // Resolved per call: the provider outlives a single request in queue workers.
        $schoolId = app(CurrentSchool::class)->id();

        return $schoolId === null
            ? $query->whereNull($column)
            : $query->where($column, $schoolId);
    }
}
