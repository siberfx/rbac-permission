<div align="center">

# 🛡️ RBAC Permission for Laravel

### Roles, permissions and *scopes* that say **who** may act and **how far** the reach goes.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/siberfx/rbac-permission.svg?style=flat-square&logo=packagist&logoColor=white)](https://packagist.org/packages/siberfx/rbac-permission)
[![Latest Release](https://img.shields.io/github/v/release/siberfx/rbac-permission?style=flat-square&logo=github&logoColor=white&label=release)](https://github.com/siberfx/rbac-permission/releases)
[![Total Downloads](https://img.shields.io/packagist/dt/siberfx/rbac-permission.svg?style=flat-square&logo=packagist&logoColor=white)](https://packagist.org/packages/siberfx/rbac-permission)
[![Tests](https://img.shields.io/github/actions/workflow/status/siberfx/rbac-permission/run-tests.yml?branch=main&style=flat-square&logo=github&logoColor=white&label=tests)](https://github.com/siberfx/rbac-permission/actions/workflows/run-tests.yml)
[![Tested with Pest](https://img.shields.io/badge/tested%20with-Pest-F46E2C?style=flat-square&logo=pestphp&logoColor=white)](https://pestphp.com)
[![PHP](https://img.shields.io/badge/PHP-8.4%20%7C%208.5-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net/releases/)
[![Laravel](https://img.shields.io/badge/Laravel-12.x%20%7C%2013.x-FF2D20?style=flat-square&logo=laravel&logoColor=white)](https://laravel.com)
[![License](https://img.shields.io/packagist/l/siberfx/rbac-permission.svg?style=flat-square)](LICENSE.md)

<br>

`billing.invoice.edit@team`

**Who** · **What** · **How far**

<br>

[Why it's different](#-why-its-different) •
[Quick start](#-quick-start) •
[Permission grammar](#-the-permission-grammar) •
[Scopes](#-the-scope-ladder) •
[Enforcement](#-enforcement-everywhere) •
[Commands](#-commands) •
[Configuration](#%EF%B8%8F-configuration) •
[Testing](#-testing)

</div>

---

## ✨ Why it's different

Most permission packages answer one question: *can this user do X?* Real applications need a second one: *on which records?*

**RBAC Permission** makes the answer to both a single string:

```text
billing.invoice.edit@team
└─────┬─────┘ └──┬──┘ └─┬─┘
   module    resource  verb        what you may do
                              @team  how far the reach goes
```

- 🧭 **Scopes are first class.** `own`, `team`, `tenant` and `global` are a ladder. A grant at a wider scope covers every record a narrower one does.
- 🔍 **One rule, every layer.** The same grant works for a single record check, a list query, a Gate call, a Blade directive, a route, a service method and a console command.
- 🧩 **Lists stay correct.** `Rbac::constrain()` turns "can see invoices at team scope" into the exact `WHERE` clause. No row leaks, no manual filtering.
- ⛔ **Denies always win.** A direct deny on one user overrides every role they hold.
- 🔎 **Every decision is explainable.** `rbac:explain` and `Rbac::explain()` show which roles and grants were considered and which one covered the record.
- 🧾 **Roles as code.** Describe roles in `config/rbac.php`, run `rbac:sync`, and review changes in pull requests.
- 🧱 **Fails loudly.** A typo like `billing.invoice.fly` is rejected at write time and at check time, never silently ignored.
- 🛠️ **Adjustable.** Add verbs, modules, scopes and resolvers without touching package code.

It fits a small blog, a multi-team SaaS, or an enterprise back office with the same vocabulary.

---

## ⚖️ How it compares

A quick look against [spatie/laravel-permission](https://github.com/spatie/laravel-permission), the most widely used Laravel permission package.

| Area | Spatie laravel-permission | RBAC Permission |
| --- | --- | --- |
| Permission model | Flat names, such as `edit articles` | `module.resource.verb@scope`, such as `billing.invoice.edit@team` |
| Record-level reach | Not built in. Write the check per model or policy | Built in: `own`, `team`, `tenant`, `global` scope ladder |
| List filtering | Not built in. Add `where` clauses yourself | `Rbac::constrain()` produces the exact `WHERE` clause |
| Wildcards | Supported | Segment wildcards, such as `billing.*@tenant` |
| Multi-tenancy | Teams support, enabled through config | Scope ladder, with team and tenant as scopes |
| Deny overrides | Not built in. Access is only granted | Denies always win over allows |
| Catalog of allowed names | Not built in. Unknown names throw when assigned | Permissions must be listed in the catalog, so typos are rejected |
| Explaining decisions | Not built in | `rbac:explain` and `Rbac::explain()` show every grant considered |
| Role hierarchy | Not built in | Ranks: actors can only manage roles below their own rank |
| Time-limited access | Not built in | Built in: `expires_at` on roles and direct grants |
| Audit | Not built in | Events, with an optional audit table |
| Multiple guards | Supported | Not supported. Checks use the default guard only |
| Ecosystem | Large user base, many third-party integrations, years of production use | New (v1.x), small user base, few integrations |

**Choose Spatie** for a standard, well-supported permission system with flat rules and a large ecosystem, or when you need multiple guards.

**Choose RBAC Permission** when access depends on which records a user may reach, when lists must be filtered by team or tenant, when you need to explain decisions, or when you want hierarchy, expiry and an audit trail built in.

*Spatie's feature list reflects its documentation at the time of writing. Check its current docs before deciding.*

---

## 🚀 Quick start

**1. Install**

```bash
composer require siberfx/rbac-permission
```

**2. Publish and run the migration**

```bash
php artisan vendor:publish --tag=rbac-migrations
php artisan migrate
```

**3. Add the trait to your user model**

```php
use Siberfx\RbacPermission\Concerns\HasRbac;

class User extends Authenticatable
{
    use HasRbac;
}
```

**4. Describe your permissions and roles** in `config/rbac.php` (see [Configuration](#%EF%B8%8F-configuration)), then sync them:

```bash
php artisan rbac:sync
```

**5. Use it**

```php
$user->assignRole('manager');

$user->can('billing.invoice.edit', $invoice);      // record check, through Gate
Rbac::allows('billing.invoice.edit', $invoice);     // same answer, anywhere
Rbac::constrain(Invoice::query(), 'billing.invoice.show')->paginate();  // only rows I may see
```

---

## 🧬 The permission grammar

Every permission is written the same way, and wildcards work in grants:

| Grant | Meaning |
| --- | --- |
| `billing.invoice.edit@team` | Edit invoices, for records in the user's team |
| `billing.invoice.*@tenant` | Every invoice verb, across the whole tenant |
| `billing.*@own` | Every billing permission, only on records the user owns |
| `*.invoice.show@global` | Show invoices anywhere, in any module |
| `*@global` | Everything (super administrator) |

A trailing `*` covers the rest of the key. A `*` in the middle matches exactly one segment.

### The verbs

| Verb | Use it for |
| --- | --- |
| `access` | Entering an area, page or module |
| `show` | Reading a record or a view |
| `create` | Making a new record |
| `edit` | Changing an existing record |
| `delete` | Removing a record |
| `run` | Executing an action, job, command or report |
| `export` | Taking data out of the system |
| `approve` | Signing off a pending change |

Add your own (for example `publish` or `impersonate`) in `config('rbac.verbs')`.

---

## 🪜 The scope ladder

Scopes answer *where*. Each one is a small class with two jobs: decide whether a single record is covered, and narrow a query to the covered rows.

```text
   global   ██████████████████████████████████████  every record
   tenant   ██████████████████████████              the user's tenant
   team     ██████████████                          the user's teams
   own      ██████                                  records the user owns
```

| Scope | Covers a record when… | Adds to a query |
| --- | --- | --- |
| `own` | the owner column equals the user | `WHERE user_id = ?` |
| `team` | the team column is one of the user's teams | `WHERE team_id IN (…)` |
| `tenant` | the tenant column equals the user's tenant | `WHERE tenant_id = ?` |
| `global` | always | nothing |

Add your own scope, such as `region`, in three steps: write a class that implements `ScopeContract`, register it under `rbac.scopes` with a `level`, and use it in a grant. A `level` decides which scope counts as "widest" in matrices and reports.

---

## 🧭 Enforcement everywhere

### Models and Eloquent

Add `HasRbacScopes` to resource models whose column names differ from the defaults:

```php
use Siberfx\RbacPermission\Concerns\HasRbacScopes;

class Invoice extends Model
{
    use HasRbacScopes;

    public function rbacOwnerColumn(): string { return 'created_by'; }
    public function rbacTeamColumn(): string  { return 'department_id'; }
}
```

### Gate and policies

Every catalog permission works with the standard authorization API:

```php
$user->can('billing.invoice.edit', $invoice);
Gate::allows('billing.invoice.approve', $refund);
$this->authorize('billing.invoice.show', $invoice);
```

Names outside the catalog are left to your own gates, so the two systems coexist.

### Blade

```blade
@rbac('billing.invoice.edit', $invoice)
    <a href="{{ route('invoices.edit', $invoice) }}">Edit</a>
@else
    <span class="muted">Read only</span>
@endrbac
```

> Use `@else`, not `@elserbac`. Blade's `@elserbac` is an `elseif` with no arguments.

### HTTP routes

Put the permission on the action, and the route stays clean:

```php
use Siberfx\RbacPermission\Attributes\RequiresPermission;

class InvoiceController
{
    #[RequiresPermission('billing.invoice.show', subject: 'invoice')]
    public function show(Invoice $invoice) { /* ... */ }

    #[RequiresPermission('billing.invoice.delete', subject: 'invoice')]
    public function destroy(Invoice $invoice) { /* ... */ }
}
```

```php
Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->middleware('rbac');
```

Or state it on the route:

```php
Route::get('/legacy/{invoice}', ...)->middleware('rbac:billing.invoice.show,invoice');
```

A scoped check must receive a real model. If the route parameter cannot be bound, the request fails closed with an error rather than being treated as a list check.

### Services and jobs

```php
use Siberfx\RbacPermission\Facades\Rbac;

public function approve(Refund $refund): void
{
    Rbac::enforce('billing.refund.approve', $refund);   // throws PermissionDenied (403)

    // ... approve the refund
}
```

### Lists and exports

```php
$invoices = Rbac::constrain(Invoice::query(), 'billing.invoice.show')
    ->latest()
    ->paginate();
```

The scope becomes SQL. Combined grants are unioned, and a direct deny is applied as `NOT (…)`.

### Console commands

```php
use Siberfx\RbacPermission\Attributes\RequiresPermission;

#[RequiresPermission('ops.cache.run')]
class ClearCache extends Command
{
    protected $signature = 'ops:cache-clear';
    // ...
}
```

The command runs as the user named in `RBAC_CONSOLE_USER_ID`. If that user lacks the permission, the command is refused before its body runs. Unattended runs, such as cron jobs without an actor, are refused as well unless you set `allow_unattended` to `true`.

---

## 🔍 Explain a decision

When access is denied and nobody knows why, ask the system:

```bash
php artisan rbac:explain billing.invoice.edit 42 --on-model="App\Models\Invoice" --on=7
```

_Example output:_

```text
Decision: ALLOW
User: App\Models\User#42
Subject: Invoice#7
Roles: manager

+----------------------------------+-----------------+----------------+
| Grant                            | Source          | Covers subject |
+----------------------------------+-----------------+----------------+
| billing.invoice.edit@team        | role:manager    | yes            |
| deny billing.invoice.edit@own    | direct          | no             |
+----------------------------------+-----------------+----------------+
```

The exit code is `0` when allowed and `1` when denied, so it works in scripts and CI. In code, use `Rbac::explain()` to get the same trace as an array.

---

## 🧰 Commands

| Command | What it does |
| --- | --- |
| `rbac:sync [--prune] [--guard=]` | Writes the roles from `config/rbac.php` to the database in one transaction, for every guard (or only `--guard`). `--prune` removes roles that are no longer defined. |
| `rbac:matrix [--only-granted] [--guard=]` | Prints the role by permission grid for one guard, showing the widest scope of each grant. |
| `rbac:explain {permission} {user} [--on-model= --on=]` | Explains a decision, with the grants that were considered. |
| `rbac:assign {role} {user} [--revoke] [--expires=]` | Gives or removes a role for a user. `--expires` makes the role time-boxed. |
| `rbac:grant {role} {permission} [--revoke] [--guard=]` | Adds or removes a grant on a role. The role is created if it is missing. |

```bash
php artisan rbac:matrix
```

_Example output:_

```text
+------------------------------+------------+---------+-------+
| Permission                   | manager    | staff   | super |
+------------------------------+------------+---------+-------+
| billing.invoice.show         | team       | own     | global|
| billing.invoice.edit         | team       | own     | global|
| billing.invoice.delete       | -          | -       | global|
| ops.cache.run                | -          | -       | global|
+------------------------------+------------+---------+-------+
```

---

## 🧑‍💼 Managing access in code

```php
use Siberfx\RbacPermission\Facades\Rbac;

Rbac::role('auditor', 'Auditor', 'Read-only access across billing');
Rbac::grant('auditor', 'billing.*@tenant');
Rbac::grant('auditor', 'reports.sales.export@tenant');

$user->assignRole('auditor');
$user->removeRole('staff');

$user->allowPermission('billing.refund.approve@global');  // direct allow
$user->denyPermission('billing.invoice.delete@own');      // direct deny, always wins
$user->withdrawPermission('billing.invoice.delete@own');  // remove the override

Rbac::widestScope('billing.invoice.show', $user);          // "tenant"
```

Every write clears the grant cache for the request, and when caching is enabled it invalidates the cache for everyone.

### Role hierarchy

Give each role a rank in `rbac.ranks`. An actor may only assign or remove a role whose rank is **strictly below** the highest rank they hold, so an admin can hand out `manager` but never `admin`.

```php
$admin = auth()->user();                        // holds "admin" (rank 80)

$target->assignRole('manager', by: $admin);     // allowed (50 < 80)
$target->assignRole('admin', by: $admin);       // throws RoleAssignmentDenied (80 is not below 80)

Rbac::rankOf($admin);                           // 80
```

Without `by:` nothing is checked, which is how seeders and trusted jobs run. `rbac:assign` checks against the configured console actor (`rbac.console`).

### Multiple guards

Roles belong to an authentication guard. Each user model says which guard it uses, and roles are resolved within it. So `manager` can mean one thing for `web` users and another for `admin` users.

```php
// config/rbac.php: the default guard uses the top-level roles and ranks
'guards' => [
    'admin' => [
        'roles' => ['manager' => ['billing.*@tenant']],
        'ranks' => ['manager' => 50],
    ],
],
```

```php
class Admin extends Authenticatable
{
    use HasRbac;

    public function rbacGuard(): string
    {
        return 'admin';  // HasRbac defaults to auth.defaults.guard
    }
}

$admin->assignRole('manager');     // resolved in the admin guard
$web->assignRole('manager');       // resolved in the default guard
```

Checks without an explicit user use the guard that is logged in. Pass the user yourself when several guards are active at once. Route middleware always uses `$request->user()`, so it follows the route's guard.

### Time-boxed grants

Roles and direct overrides accept an optional expiry. Once it passes, the grant stops applying, with no job needed.

```php
$user->assignRole('manager', expiresAt: now()->addDays(30));  // covering for a colleague
$user->allowPermission('billing.refund.approve@global', now()->addHours(4));
$user->denyPermission('billing.invoice.delete@own', now()->addDay());  // temporary lock-out

Rbac::pruneExpired();  // optional: delete expired rows, e.g. from a daily schedule
```

```bash
php artisan rbac:assign staff 42 --expires="+7 days"
```

Expired rows are ignored at once, even if they are still in the table. Assigning a role again without an expiry makes it permanent.

When the grant cache is enabled (`rbac.cache.ttl > 0`), a grant can keep applying for up to `ttl` seconds after it expires. Keep the TTL short if that matters to you.

### Audit log

Every change to roles, assignments and direct grants fires `Siberfx\RbacPermission\Events\RbacChanged`. Turn on `rbac.audit.enabled` to also store each change in `rbac_audit_logs`:

```php
// config/rbac.php
'audit' => ['enabled' => true],
```

| `action` | Fired when |
| --- | --- |
| `role.granted` / `role.revoked` | A permission is added to or removed from a role |
| `role.assigned` / `role.unassigned` | A role is given to or removed from a user |
| `permission.allowed` / `permission.denied` / `permission.withdrawn` | A direct override is written or removed |
| `roles.synced` | `rbac:sync` or `Rbac::sync()` runs |
| `grants.pruned` | `Rbac::pruneExpired()` removes expired rows |

Each row stores the actor (the `by:` user if you passed one, otherwise the authenticated user), the subject, and the permission, scope, effect and expiry involved. Changes that remove nothing are not recorded.

Listen for the event for your own reporting or notifications:

```php
Event::listen(RbacChanged::class, function (RbacChanged $event) {
    if ($event->action === 'role.assigned') {
        Log::info('role assigned', ['by' => $event->actor?->getKey(), 'to' => $event->subject?->getKey()]);
    }
});
```

Audit writes run inside the same request as the change. If the change is in a database transaction that rolls back, its audit row rolls back too.

---

## ⚙️ Configuration

Publish the file to change anything:

```bash
php artisan vendor:publish --tag=rbac-config
```

| Key | Purpose |
| --- | --- |
| `tables` | Table names, if they clash with yours |
| `default_scope` | Scope used when a grant has no `@scope`. Default: `own` |
| `verbs` | The allowed verb vocabulary |
| `modules` | The catalog of every permission in your application |
| `scopes` | The scope ladder: name, `level`, resolver class |
| `columns` | Default owner, team and tenant columns on resource models |
| `user.teams_relation` | Relation on the user that returns teams |
| `roles` | Roles as code, applied with `rbac:sync` |
| `console` | Console actor and unattended policy |
| `cache` | Optional cross-request grant cache (`ttl` in seconds, `0` disables) |
| `register_gate` | Whether catalog permissions are registered with the Gate |

<details>
<summary><b>A complete example of <code>modules</code> and <code>roles</code></b></summary>

```php
'modules' => [
    'billing' => [
        'label' => 'Billing',
        'resources' => [
            'invoice' => ['show', 'create', 'edit', 'delete', 'export', 'approve'],
            'refund'  => ['show', 'create', 'approve'],
        ],
    ],
    'ops' => [
        'label' => 'Operations',
        'resources' => [
            'cache' => ['run'],
        ],
    ],
],

'roles' => [
    'super-admin' => ['*@global'],

    'admin' => ['billing.*@tenant'],

    'manager' => [
        'billing.invoice.show@team',
        'billing.invoice.edit@team',
        'billing.invoice.approve@team',
    ],

    'staff' => [
        'billing.invoice.show@own',
        'billing.invoice.create@own',
        'billing.invoice.edit@own',
    ],
],
```

</details>

---

## 🗄️ Database schema

| Table | Holds |
| --- | --- |
| `rbac_roles` | Role slug, guard, name, description and rank (slug is unique per guard) |
| `rbac_role_grants` | The permission and scope each role carries |
| `rbac_role_assignments` | Which user holds which role, and until when (polymorphic) |
| `rbac_user_grants` | Direct allows and denies on a user, and until when (polymorphic) |
| `rbac_audit_logs` | Change history, when `rbac.audit.enabled` is true |

Model ids are stored as strings, so integer, UUID and ULID keys all work.

---

## 🧪 Testing

The package is tested with [Pest](https://pestphp.com) on Testbench, with an in-memory SQLite database.

```bash
composer install
composer test
```

Or run it directly:

```bash
vendor/bin/pest
```

The suite covers the grammar and wildcards, every scope and deny overrides, query constraints, Gate and Blade integration, HTTP enforcement from attributes and route arguments, console guarding, roles as code, the matrix and explanations.

In your own application, reset the grant cache between tests if you enable it:

```php
Rbac::flush();
```

---

## 🗺️ Roadmap

- [x] Role hierarchy with "can only assign roles below mine" (v1.1.0)
- [x] Time-boxed grants (`expires_at`) (v1.2.0)
- [x] Permission audit log through the event system (v1.3.0)

Ideas and pull requests are welcome.

---

## 🤝 Contributing

1. Fork the repository and create a feature branch.
2. Add a Pest test that fails without your change.
3. Run `composer test` and make sure it passes.
4. Open a pull request with a clear description.

Please follow [Semantic Versioning](https://semver.org/) for releases. Notable changes go into [CHANGELOG.md](CHANGELOG.md).

---

## 🏷️ Versioning and releases

Releases are tagged on `main` with a `v` prefix, for example `v1.0.0`. Install a specific version with a constraint:

```bash
composer require siberfx/rbac-permission:^1.0
```

Each release is listed on the [Releases page](https://github.com/siberfx/rbac-permission/releases) with its changes.

---

## 👤 Author

**Selim Gormus** · [@siberfx](https://github.com/siberfx) · [info@siberfx.com](mailto:info@siberfx.com)

---

## 📄 License

The MIT License (MIT). See [LICENSE.md](LICENSE.md) for details.

<div align="center">

**If this package saves you time, a ⭐ on GitHub helps others find it.**

</div>
