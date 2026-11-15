<?php

use Siberfx\RbacPermission\Scopes\GlobalScope;
use Siberfx\RbacPermission\Scopes\OwnScope;
use Siberfx\RbacPermission\Scopes\TeamScope;
use Siberfx\RbacPermission\Scopes\TenantScope;

/*
|--------------------------------------------------------------------------
| RBAC Permission
|--------------------------------------------------------------------------
|
| A permission is written as   module.resource.verb@scope
|
|   billing.invoice.edit@team   edit invoices, for records in my team
|   billing.invoice.*@tenant    every invoice verb, across my tenant
|   billing.*                   every billing permission (trailing wildcard)
|   *@global                    everything (super admin)
|
| The part before "@" is WHAT you may do. The part after "@" is WHERE: how far
| the grant reaches. Scopes are ordered by "level"; a wider scope covers every
| record a narrower one does.
|
*/

return [

    /*
    |----------------------------------------------------------------------
    | Tables
    |----------------------------------------------------------------------
    | Change these before publishing the migration if they clash with yours.
    */
    'tables' => [
        'roles' => 'rbac_roles',
        'role_grants' => 'rbac_role_grants',
        'role_assignments' => 'rbac_role_assignments',
        'user_grants' => 'rbac_user_grants',
    ],

    /*
    |----------------------------------------------------------------------
    | Default scope
    |----------------------------------------------------------------------
    | Used when a grant is written without "@scope". Least privilege by default.
    */
    'default_scope' => 'own',

    /*
    |----------------------------------------------------------------------
    | Verbs (the vocabulary of the "verb" segment)
    |----------------------------------------------------------------------
    | show    read a record or view        access  enter an area, page or module
    | create  make a new record            edit    change an existing record
    | delete  remove a record              run     execute an action, job, command or report
    | export  take data out                approve a pending change
    | Add your own (e.g. "publish", "impersonate") here.
    */
    'verbs' => ['show', 'access', 'create', 'edit', 'delete', 'run', 'export', 'approve'],

    /*
    |----------------------------------------------------------------------
    | Module catalog
    |----------------------------------------------------------------------
    | Every permission that exists in the application. Grants that match no
    | catalog entry are rejected, so typos never silently do nothing.
    */
    'modules' => [
        'billing' => [
            'label' => 'Billing',
            'resources' => [
                'invoice' => ['show', 'create', 'edit', 'delete', 'export', 'approve'],
                'refund' => ['show', 'create', 'approve'],
            ],
        ],
        'reports' => [
            'label' => 'Reports',
            'resources' => [
                'sales' => ['access', 'show', 'export'],
            ],
        ],
        'ops' => [
            'label' => 'Operations',
            'resources' => [
                'cache' => ['run'],
                'queue' => ['run'],
            ],
        ],
        'admin' => [
            'label' => 'Administration',
            'resources' => [
                'panel' => ['access'],
                'user' => ['show', 'create', 'edit', 'delete'],
                'role' => ['show', 'edit'],
            ],
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Scopes (the "where" ladder)
    |----------------------------------------------------------------------
    | resolver: class implementing Siberfx\RbacPermission\Scopes\ScopeContract
    | level:    higher = wider reach. Used for "widest grant" and the matrix.
    */
    'scopes' => [
        'own' => ['level' => 10, 'resolver' => OwnScope::class],
        'team' => ['level' => 20, 'resolver' => TeamScope::class],
        'tenant' => ['level' => 30, 'resolver' => TenantScope::class],
        'global' => ['level' => 40, 'resolver' => GlobalScope::class],
    ],

    /*
    |----------------------------------------------------------------------
    | Columns used by the scopes
    |----------------------------------------------------------------------
    | On a resource model: who owns it, which team and tenant it belongs to.
    | Override per model with the HasRbacScopes trait.
    */
    'columns' => [
        'owner' => 'user_id',
        'team' => 'team_id',
        'tenant' => 'tenant_id',
    ],

    /*
    |----------------------------------------------------------------------
    | User relations
    |----------------------------------------------------------------------
    | Relation on the user model that returns its teams (for the team scope).
    */
    'user' => [
        'teams_relation' => 'teams',
    ],

    /*
    |----------------------------------------------------------------------
    | Roles as code
    |----------------------------------------------------------------------
    | Define roles here and run `php artisan rbac:sync` to write them to the
    | database. Roles not listed here are left alone unless you pass --prune.
    */
    'roles' => [
        'super-admin' => ['*@global'],

        'admin' => [
            'billing.*@tenant',
            'reports.*@tenant',
            'admin.*@tenant',
        ],

        'manager' => [
            'billing.invoice.show@team',
            'billing.invoice.create@team',
            'billing.invoice.edit@team',
            'billing.invoice.approve@team',
            'reports.sales.access@team',
            'reports.sales.export@team',
        ],

        'staff' => [
            'billing.invoice.show@own',
            'billing.invoice.create@own',
            'billing.invoice.edit@own',
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Role ranks (hierarchy)
    |----------------------------------------------------------------------
    | A higher rank outranks a lower one. An actor may only assign or remove a
    | role whose rank is strictly below the highest rank they hold, so an admin
    | can hand out "manager" but never "admin". Roles not listed keep rank 0.
    | Pass the actor as the third argument of Rbac::assign() / unassign().
    */
    'ranks' => [
        'super-admin' => 100,
        'admin' => 80,
        'manager' => 50,
        'staff' => 10,
    ],

    /*
    |----------------------------------------------------------------------
    | Console
    |----------------------------------------------------------------------
    | Commands marked with #[RequiresPermission] run as this user. Set
    | RBAC_CONSOLE_USER_ID, or supply a resolver closure. Unattended runs
    | (cron, deploy hooks) are denied unless allow_unattended is true.
    */
    'console' => [
        'user_id' => env('RBAC_CONSOLE_USER_ID'),
        'resolver' => null,
        'allow_unattended' => false,
    ],

    /*
    |----------------------------------------------------------------------
    | Grant cache
    |----------------------------------------------------------------------
    | ttl = 0 disables the cross-request cache (grants are still memoised per
    | request). Any change made through the Rbac API invalidates the cache.
    */
    'cache' => [
        'store' => null,
        'ttl' => 0,
    ],

    /*
    |----------------------------------------------------------------------
    | Gate integration
    |----------------------------------------------------------------------
    | When true, every catalog permission works with $user->can(...), @can
    | and Gate::allows(...). Names that are not in the catalog are untouched.
    */
    'register_gate' => true,
];
