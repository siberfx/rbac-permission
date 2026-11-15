<?php

namespace Siberfx\RbacPermission\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Siberfx\RbacPermission\Concerns\HasRbacScopes;

class Invoice extends Model
{
    use HasRbacScopes;

    protected $table = 'invoices';

    protected $guarded = [];

    public $timestamps = false;
}
