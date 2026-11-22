<?php

declare(strict_types=1);

namespace Siberfx\RbacPermission\Scopes;

use Illuminate\Database\Eloquent\Model;
use Siberfx\RbacPermission\Support\Columns;

/**
 * "team": records whose team column is one of the user's teams.
 */
final class TeamScope implements ExcludesRows, ScopeContract
{
    public function covers(Model $user, Model $subject): bool
    {
        $team = Columns::key($subject, 'team');

        return $team !== null && in_array((string) $team, Columns::teamIds($user), true);
    }

    public function constrain($query, Model $user, Model $model): void
    {
        $teams = Columns::teamIds($user);

        if ($teams === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn($model->qualifyColumn(Columns::name($model, 'team')), $teams);
    }

    public function exclude($query, Model $user, Model $model): void
    {
        $teams = Columns::teamIds($user);

        // No teams: the scope reaches nothing, so a deny removes nothing.
        if ($teams === []) {
            return;
        }

        $column = $model->qualifyColumn(Columns::name($model, 'team'));

        $query->whereNull($column)->orWhereNotIn($column, $teams);
    }
}
