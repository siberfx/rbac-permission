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
        $this->authorizeAction($request, 'admin.user.show');

        $user = $this->userModel()::query()->findOrFail($id);

        return view('rbac::users.show', [
            'user' => $user,
            'assigned' => $user->roles()->orderBy('slug')->get(),
            'available' => Role::query()->where('guard', $user->rbacGuard())->orderByDesc('rank')->orderBy('slug')->get(),
        ]);
    }

    public function assign(Request $request, int|string $id): RedirectResponse
    {
        $actor = $this->authorizeAction($request, 'admin.user.edit');
        $user = $this->userModel()::query()->findOrFail($id);

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
        $actor = $this->authorizeAction($request, 'admin.user.edit');
        $user = $this->userModel()::query()->findOrFail($id);

        try {
            $this->rbac->unassign($user, $slug, $actor);
        } catch (RoleAssignmentDenied|InvalidArgumentException $e) {
            return back()->withErrors(['role' => $e->getMessage()]);
        }

        return back()->with('status', "Removed [{$slug}].");
    }

    /**
     * Checks the permission for the signed-in user and returns them.
     */
    private function authorizeAction(Request $request, string $permission): Model
    {
        $actor = $request->user();

        if (! $actor instanceof Model) {
            throw new AuthenticationException;
        }

        $this->rbac->enforce($permission, null, $actor);

        return $actor;
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
     * @return class-string<Model>
     */
    private function userModel(): string
    {
        return (string) config('auth.providers.users.model');
    }
}
