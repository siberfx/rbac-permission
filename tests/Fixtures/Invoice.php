<?php

declare(strict_types=1);

namespace Siberfx\RbacPermission\Tests\Fixtures;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Siberfx\RbacPermission\Concerns\HasRbacScopes;

class Invoice extends Model
{
    use CrudTrait;
    use HasRbacScopes;

    protected $table = 'invoices';

    protected $guarded = [];

    public $timestamps = false;
}
