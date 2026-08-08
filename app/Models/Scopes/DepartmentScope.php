<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class DepartmentScope implements Scope
{
    /**
     * The relationship name to the User model.
     *
     * @var string
     */
    protected string $userRelation;

    public function __construct(string $userRelation = 'user')
    {
        $this->userRelation = $userRelation;
    }

    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        $user = Auth::user();

        // If no user is logged in, or the user is an admin/superadmin, do not apply scope.
        if (! $user || $user->hasRole('admin', 'superadmin') || ! $user->department_id) {
            return;
        }

        // Apply department isolation based on the specified user relation
        $builder->whereHas($this->userRelation, function (Builder $query) use ($user) {
            $query->where('department_id', $user->department_id);
        });
    }
}
