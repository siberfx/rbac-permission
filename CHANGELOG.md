# Changelog

All notable changes to `siberfx/rbac-permission` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/).

## [1.8.4] - 2026-11-22

### Added

- CI runs the suite on MySQL 8.4, MariaDB 11 and PostgreSQL 17 (Laravel 12 and 13), next to SQLite. It also runs one job with the grant cache on. The package needed no changes: all 132 tests pass on every database.
- The test suite can target a database server: `RBAC_TEST_DB=mysql|mariadb|pgsql` with `RBAC_TEST_DB_HOST`, `_PORT`, `_DATABASE`, `_USERNAME` and `_PASSWORD`. `RBAC_TEST_CACHE=1` turns the grant cache on for every test. See "Testing" in the README.

### Changed

- Static analysis raised from level 5 to level 6. Generics and array shapes are now declared: `Rbac::constrain()` keeps the query's model type, and the facade lists the shapes of `explain()`, `sync()` and `matrix()`.
- The README roadmap lists everything shipped up to 1.8.4 and what is planned next.

### Fixed

- Test setup on database servers: `RefreshDatabase` dropped the tables the setup had just built, and on a fresh database the console tests ran without Laravel's rerouted command events. Neither affected the package itself.

## [1.8.3] - 2026-11-22

### Added

- `php artisan rbac:prune-expired`: deletes role assignments and direct grants whose expiry has passed. Schedule it daily to keep the tables small.
- `Rbac::rolesOf($user)` and `Rbac::guardOf($user)`: a user's role relation and guard for any user model, with or without `HasRbac`. `HasRbac::roles()` now returns `Rbac::rolesOf()`.
- Coding standards with Pint (`pint.json`): the Laravel preset, plus imported classes instead of inline fully qualified names (also in docblocks), sorted imports, no unused imports, and `declare(strict_types=1);` in every PHP file.
- Static analysis with Larastan at level 5 (`phpstan.neon.dist`).
- A CI job runs both. Composer scripts: `composer lint`, `composer format`, `composer analyse`, and `composer check` for everything plus the tests.

### Changed

- Every PHP file now declares `strict_types`. Arguments passed to the package keep working. The declaration only affects calls made inside the package's own files.
- The models use `$fillable` instead of `$guarded = []`, cast `rank`, `role_id` and `expires_at` (now a `Carbon` instance on `UserGrant`), and document their properties.
- `rbac:assign` and `rbac:explain` check that `--model` / `--on-model` name an Eloquent model class, and say so when they do not.
- The admin user page and the Filament user roles screen no longer require the user model to use `HasRbac`.

### Fixed

- The Filament role edit check now uses `Rbac::canEditRole()` and accepts only `Role` records.
- The commands table in the README was split by a misplaced paragraph.

## [1.8.2] - 2026-11-22

### Fixed

- RBAC queries used different database connections: assignments went through the user model's connection, `pruneExpired()`, `sync()` and the audit log through the default connection, and the models through their own. Everything now uses one connection.
- The `global` scope was recognised by its name. Renaming it, or adding another top scope, silently lost its "no row filter" and "deny hides list pages" behaviour. Scopes that reach every record are now recognised by the new `Unrestricted` interface, which `GlobalScope` implements.
- Assigning a role the user already holds, with the same expiry, no longer rewrites the row or records a second `role.assigned` audit entry. Changing the expiry keeps the assignment's original `created_at`.
- The admin user pages, `rbac:assign`, `rbac:explain` and the console actor only knew `auth.providers.users.model`, so users of another guard could not be managed. They now resolve the user model from the guard's auth provider.

### Added

- `rbac.connection` (`RBAC_DB_CONNECTION`): the database connection for every RBAC table. The migration creates the tables on it. `null`, the default, keeps the default connection.
- `Siberfx\RbacPermission\Scopes\Unrestricted`: marker interface for custom scopes that reach every record.
- `Rbac::userModel(?string $guard)`: the user model of a guard.
- `--guard` on `rbac:assign` and `rbac:explain`, `?guard=` on the admin user pages, and `rbac.console.guard` for the console actor.
- The `Rbac` facade docblock now lists every public method and its current signature.

## [1.8.1] - 2026-11-22

### Changed

- Faster permission checks. The grants that match a permission key are now looked up once per user and key in each request. Before, every check scanned all of the user's grants. Expiry is still checked on every call.
- `rbac:sync` writes only the grants that changed. Unchanged grants keep their rows and ids, and a grant listed twice in config is stored once. Before, every run deleted and recreated every grant. `Rbac::sync()` also returns `added` and `removed` counts, and the command prints them.
- The console listener behind `#[RequiresPermission]` now loads only the command being run. Before, it built every registered command on each `artisan` call through `Artisan::all()`, which is still the fallback.

## [1.8.0] - 2026-11-22

### Security

- Admin screens: viewing a user and assigning or removing their roles now checks `admin.user.show` / `admin.user.edit` against that user. Before, a tenant- or team-scoped admin could manage users of any tenant or team.
- Filament: the user roles relation manager and `Operations::assignRole()` / `unassignRole()` apply the same check. Both operations now throw `PermissionDenied` when the actor does not reach the user.
- Changing a role's grants now follows the rank rules, like assigning a role. An actor can only add or remove grants on roles strictly below their own rank, so nobody can edit their own role or one above it. This applies to the Blade admin screens and to Filament `Operations::grant()` / `revoke()`. Before, it was enforced only by the Filament edit page.
- `Rbac::canGrant()` now refuses a permission the actor is denied at any scope, even when a role also allows it to them.

### Fixed

- `php artisan migrate` failed on a fresh install. The published migrations had no date prefix, so they ran in alphabetical order and `add_*` ran before `create_rbac_tables`. The five migrations are now merged into one, `create_rbac_permission_tables`, published with a date prefix. Publishing again does not create a duplicate.
- Stale permissions in long-running processes. `Rbac` is now bound as `scoped` instead of `singleton`, so Octane workers and `queue:work` start every request or job with fresh grants. Before, a change made in another process was not seen until the worker restarted.
- With the grant cache on (`rbac.cache.ttl > 0`), a role assignment or direct grant kept working after its `expires_at` until the cache entry expired. Each cached grant now carries its expiry and stops applying on time. Cache keys changed (`rbac.grants.v2.*`), so entries cached by older versions are ignored.
- The grant cache is invalidated after the surrounding database transaction commits, not before. Before, another request could load the old rows mid-transaction and cache them under the new version, which left stale permissions until the TTL ran out. A rolled-back change no longer invalidates the cache.
- `Rbac::constrain()` hid rows whose owner, team or tenant column is NULL whenever the user had an `@own`, `@team` or `@tenant` deny, while `allows()` on the same row returned true. Denies on the built-in scopes now exclude only rows they actually cover.

### Added

- `Rbac::canEditRole($actor, $role)`: whether the actor may change the role's grants.
- `Siberfx\RbacPermission\Scopes\ExcludesRows`: optional interface for custom scopes, to remove their rows NULL-safely when denied. Scopes without it keep the previous `NOT (...)` behaviour.
- `Siberfx\RbacPermission\Support\Grant` gained `expiresAt` and `isActive()`.
- `RbacPermissionServiceProvider::MIGRATIONS` lists the package migrations in run order. Future schema changes will be new guarded migrations added at the end.

### Changed

- One migration for the whole schema: `create_rbac_permission_tables` replaces `create_rbac_tables`, `add_rank_to_rbac_roles`, `add_expires_at_to_rbac_grants`, `create_rbac_audit_logs_table` and `add_guard_to_rbac_roles`. It checks every table and column first. On an app that already has the tables it adds only what is missing, such as `rank`, `guard` or `expires_at` from a partial upgrade, and running it twice does nothing. Its rollback does not drop tables when any of the older migrations is recorded in the app's migrations table.

### Upgrading

- Apps that already ran the older migrations need no action. Those migrations stay in your app and keep working. If you publish again, the new migration is added next to them, and running it is safe: it only adds what is missing.
- Role editors need a rank above the roles they edit. A role-editor role that held only `admin.role.edit` and had rank 0 can no longer change any role's grants. Give it a rank in `rbac.ranks`, e.g. `'role-editor' => 60`, and run `php artisan rbac:sync`.
- If you call `Operations::assignRole()` / `unassignRole()` yourself, the actor now needs `admin.user.edit` reaching the target user.

## [1.7.0] - 2026-11-16

### Added

- Backpack 7 support (`backpack/crud` 7.0.47 or later, optional and listed in `suggest`):
  - `Siberfx\RbacPermission\Backpack\HasRbacAccess`: add to a CRUD controller and call `applyRbacAccess()` in `setup()`. Each Backpack operation maps to a catalog verb. Lists are narrowed with `Rbac::constrain()`, and single records are checked against their scope. Access rules already set on the panel still apply.
  - `Siberfx\RbacPermission\Backpack\HasBackpackRbac`: for the admin user model. Its roles live in the Backpack guard (`backpack.base.guard`).
- `Siberfx\RbacPermission\Backpack\Access`: the logic behind the trait, usable with any `CrudPanel`.

### Upgrading

- No migration. Install `backpack/crud` 7.0.47 or later to use the Backpack integration.

## [1.6.0] - 2026-11-16

### Added

- Filament 5 integration. `Siberfx\RbacPermission\Filament\RbacPlugin` adds a Roles resource to a panel. Filament is optional (`suggest`) and is required only when you use the plugin.
- Roles resource: lists the roles of every guard and edits name, description and rank. Editing is limited to roles below the signed-in user's rank, and a rank cannot be set at or above their own.
- Grants relation manager on each role: add and remove grants, each checked with `Rbac::canGrant()`.
- `UserRolesRelationManager` for your user resource: shows a user's roles and expiry, and assigns or removes them under the rank rules.
- `Siberfx\RbacPermission\Filament\Support\Operations`: the write operations behind the screens, so they can be used and tested without the UI.

### Upgrading

- No migration. Install Filament 5.8.3 or later to use the plugin.

## [1.5.0] - 2026-11-16

### Added

- Admin screens: Blade pages for roles, role grants and user role assignments, served under `rbac.ui.prefix`. Off by default (`rbac.ui.enabled`).
- Each page checks a catalog permission: `admin.role.show`, `admin.role.edit`, `admin.user.show`, `admin.user.edit`.
- `Rbac::canGrant()`. An actor can only put a permission on a role if they hold every matching permission at that scope or wider. This stops role editors from handing out access they lack.

### Upgrading

- No migration. To use the screens, set `rbac.ui.enabled` to `true`.

## [1.4.0] - 2026-11-16

### Added

- Multiple authentication guards. Roles carry a `guard`, and a slug is unique per guard, so `manager` can exist for `web` and `admin` with different grants.
- `HasRbac::rbacGuard()` says which guard a user model uses (defaults to `auth.defaults.guard`). Roles, ranks and grants are resolved within that guard.
- `rbac.guards` config: roles and ranks for each additional guard. `rbac:sync` writes every guard unless `--guard` is given.
- `--guard` option on `rbac:sync`, `rbac:matrix` and `rbac:grant`.
- Checks without an explicit user use the first logged-in user across the default and configured guards.

### Changed

- `Rbac::role()`, `grant()`, `revoke()`, `sync()` and `matrix()` accept an optional guard. String role names in `assign()` and `unassign()` are resolved in the user's guard.

### Upgrading

- Publish and run the new migration `add_guard_to_rbac_roles`. Existing roles get the default guard. The unique index on `slug` is replaced by one on `(guard, slug)`.

## [1.3.0] - 2026-11-16

### Added

- `Siberfx\RbacPermission\Events\RbacChanged` is dispatched after every change to roles, assignments and direct grants. Actions: `role.granted`, `role.revoked`, `role.assigned`, `role.unassigned`, `permission.allowed`, `permission.denied`, `permission.withdrawn`, `roles.synced`, `grants.pruned`.
- Optional audit log. With `rbac.audit.enabled = true`, each event is stored in `rbac_audit_logs` (actor, subject, permission, scope, effect, expiry, properties).

### Upgrading

- Publish and run the new migration `create_rbac_audit_logs_table` if you plan to enable the audit log. Nothing reads or writes the table while `rbac.audit.enabled` is false.

## [1.2.0] - 2026-11-16

### Added

- Time-boxed grants. `assign()`, `assignRole()`, `allow()`, `deny()`, `allowPermission()` and `denyPermission()` accept an optional expiry. Expired role assignments and direct grants stop applying immediately.
- `Rbac::pruneExpired()` deletes expired rows.
- `rbac:assign --expires=` (for example `"+7 days"`).
- `HasRbac::hasRole()` ignores expired assignments, and `roles()` exposes the `expires_at` pivot.

### Changed

- Re-assigning a role or re-issuing a direct grant updates its expiry. Omitting the date makes it permanent again.

### Upgrading

- Publish and run the new migration `add_expires_at_to_rbac_grants` (adds `expires_at` to `rbac_role_assignments` and `rbac_user_grants`).

## [1.1.0] - 2026-11-16

### Added

- Role hierarchy. Roles carry a `rank` (set in `rbac.ranks`, applied by `rbac:sync`). An actor may only assign or remove a role strictly below their highest rank.
- `Rbac::assign()`, `Rbac::unassign()`, `HasRbac::assignRole()` and `removeRole()` accept an optional actor (`by:`). Without one nothing is checked, as before.
- `Rbac::rankOf()` and `Rbac::canAssign()`.
- `Siberfx\RbacPermission\Exceptions\RoleAssignmentDenied`, raised when the hierarchy blocks a change.

### Upgrading

- Publish and run the new migration `add_rank_to_rbac_roles` (adds `rank` to `rbac_roles`, default `0`).

## [1.0.0] - 2026-11-15

First stable release.

### Added

- Permission grammar `module.resource.verb@scope` with segment wildcards (`*`, `billing.*`, `*@global`).
- Scope ladder `own` < `team` < `tenant` < `global`, configurable and extendable with custom scope resolvers.
- Role and direct user grants, with deny overrides that always win over allows.
- Query constraints: `Rbac::constrain()` narrows an Eloquent query to the rows the user may reach.
- Enforcement points for models (`HasRbac`, `HasRbacScopes`), Gate (`$user->can()`, `@can`), Blade (`@rbac`), HTTP (`rbac` middleware, `#[RequiresPermission]` on controller actions), services (`Rbac::enforce()`), and console commands (`#[RequiresPermission]` on commands).
- Explainable decisions with `Rbac::explain()` and `rbac:explain`.
- Roles as code in `config/rbac.php`, synced with `rbac:sync` in one transaction.
- Commands: `rbac:sync`, `rbac:matrix`, `rbac:explain`, `rbac:assign`, `rbac:grant`.
- Optional cross-request grant cache with automatic invalidation.
- Test suite written with Pest, run in CI against PHP 8.4 and 8.5 and Laravel 12 and 13.
