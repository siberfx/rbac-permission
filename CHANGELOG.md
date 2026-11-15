# Changelog

All notable changes to `siberfx/rbac-permission` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/).

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
