<?php

declare(strict_types=1);

namespace Siberfx\RbacPermission\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Siberfx\RbacPermission\Concerns\HasRbac;

class User extends Authenticatable
{
    use HasRbac;

    protected $table = 'users';

    protected $guarded = [];

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_user', 'user_id', 'team_id');
    }
}
