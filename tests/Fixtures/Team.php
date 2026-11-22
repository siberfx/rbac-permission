<?php

declare(strict_types=1);

namespace Siberfx\RbacPermission\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $table = 'teams';

    protected $guarded = [];

    public $timestamps = false;
}
