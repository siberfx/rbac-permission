<?php

namespace Siberfx\RbacPermission\Http\Controllers;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use InvalidArgumentException;
use Siberfx\RbacPermission\Exceptions\InvalidPermission;
use Siberfx\RbacPermission\Exceptions\PermissionDenied;
use Siberfx\RbacPermission\Exceptions\RoleAssignmentDenied;
use Siberfx\RbacPermission\Models\Role;
use Siberfx\RbacPermission\Rbac;

/**
 * The admin screens. Every action checks a catalog permission for the signed-in user.
 */
final class RbacAdminController extends Controller
{
    public function __construct(private readonly Rbac $rbac) {}

    public function roles(Request $request): View
    {
        $this->authorizeAction($request, 'admin.role.show');

        $guard = $this->guard($request);

        return view('rbac::roles.index', [
            'guard' => $guard,
            'guards' => $this->guards(),
            'roles' => Role::query()->where('guard', $guard)->withCount('grants')
                ->orderByDesc('rank')->orderBy('slug')->get(),
        ]);
    }

    public function role(Request $request, Role $role): View
    {
        $this->authorizeAction($request, 'admin.role.show');

        return view('rbac::roles.show', ['role' => $role->load('grants')]);
    }

    public function addGrant(Request $request, Role $role): RedirectResponse
    {
        $actor = $this->authorizeAction($request, 'admin.role.edit');
        $permission = $this->permissionInput($request);

        $this->assertCanEditRole($actor, $role);
        $this->assertCanGrant($actor, $permission);

        try {
            $this->rbac->grant($role, $permission);
        } catch (InvalidArgumentException|InvalidPermission $e) {
            return back()->withErrors(['permission' => $e->getMessage()])->withInput();
        }

        return back()->with('status', "Granted [{$permission}] to [{$role->slug}].");
    }

    public function removeGrant(Request $request, Role $role): RedirectResponse
    {
        $actor = $this->authorizeAction($request, 'admin.role.edit');
        $permission = $this->permissionInput($request);

        $this->assertCanEditRole($actor, $role);
        $this->assertCanGrant($actor, $permission);

        try {
            $this->rbac->revoke($role, $permission);
        } catch (InvalidArgumentException|InvalidPermission $e) {
            return back()->withErrors(['permission' => $e->getMessage()]);
        }

        return back()->with('status', "Removed [{$permission}] from [{$role->slug}].");
    }

    public function user(Request $request, int|string $id): View
    {
        $user = $this->userModel($request)::query()->findOrFail($id);

        // The user is the subject, so a tenant or team grant only reaches users in that tenant or team.
        $this->authorizeAction($request, 'admin.user.show', $user);

        return view('rbac::users.show', [
            'user' => $user,
            'guard' => $this->guard($request),
            'assigned' => $user->roles()->orderBy('slug')->get(),
            'available' => Role::query()->where('guard', $user->rbacGuard())->orderByDesc('rank')->orderBy('slug')->get(),
        ]);
    }

    public function assign(Request $request, int|string $id): RedirectResponse
    {
        $user = $this->userModel($request)::query()->findOrFail($id);
        $actor = $this->authorizeAction($request, 'admin.user.edit', $user);

        $data = $request->validate([
            'role' => ['required', 'string', 'max:64'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        try {
            $this->rbac->assign(
                $user,
                $data['role'],
                $actor,
                isset($data['expires_at']) ? Carbon::parse($data['expires_at']) : null,
            );
        } catch (RoleAssignmentDenied|InvalidArgumentException $e) {
            return back()->withErrors(['role' => $e->getMessage()])->withInput();
        }

        return back()->with('status', "Assigned [{$data['role']}].");
    }

    public function unassign(Request $request, int|string $id, string $slug): RedirectResponse
    {
        $user = $this->userModel($request)::query()->findOrFail($id);
        $actor = $this->authorizeAction($request, 'admin.user.edit', $user);

        try {
            $this->rbac->unassign($user, $slug, $actor);
        } catch (RoleAssignmentDenied|InvalidArgumentException $e) {
            return back()->withErrors(['role' => $e->getMessage()]);
        }

        return back()->with('status', "Removed [{$slug}].");
    }

    /**
     * Checks the permission for the signed-in user and returns them.
     * Pass the record being acted on, so the grant's scope is checked against it.
     */
    private function authorizeAction(Request $request, string $permission, ?Model $subject = null): Model
    {
        $actor = $request->user();

        if (! $actor instanceof Model) {
            throw new AuthenticationException;
        }

        $this->rbac->enforce($permission, $subject, $actor);

        return $actor;
    }

    private function assertCanEditRole(Model $actor, Role $role): void
    {
        if (! $this->rbac->canEditRole($actor, $role)) {
            throw new PermissionDenied('admin.role.edit', 'none', "You may not change [{$role->slug}]: it is not below your rank.");
        }
    }

    private function assertCanGrant(Model $actor, string $permission): void
    {
        if (! $this->rbac->canGrant($actor, $permission)) {
            throw new PermissionDenied($permission, 'none', "You may not grant [{$permission}]: you do not hold it at that scope.");
        }
    }

    private function permissionInput(Request $request): string
    {
        return (string) $request->validate([
            'permission' => ['required', 'string', 'max:191'],
        ])['permission'];
    }

    private function guard(Request $request): string
    {
        $guard = (string) $request->query('guard', $this->defaultGuard());

        return in_array($guard, $this->guards(), true) ? $guard : $this->defaultGuard();
    }

    /**
     * @return list<string>
     */
    private function guards(): array
    {
        return array_values(array_unique([$this->defaultGuard(), ...array_keys((array) config('rbac.guards', []))]));
    }

    private function defaultGuard(): string
    {
        return (string) config('auth.defaults.guard', 'web');
    }

    /**
     * The user model of the guard in ?guard= (one of the configured guards), or of the default guard.
     *
     * @return class-string<Model>
     */
    private function userModel(Request $request): string
    {
        return $this->rbac->userModel($this->guard($request));
    }
}
