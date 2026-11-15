<?php

namespace Siberfx\RbacPermission\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use LogicException;
use ReflectionClass;
use ReflectionMethod;
use Siberfx\RbacPermission\Attributes\RequiresPermission;
use Siberfx\RbacPermission\Rbac;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alias: "rbac". Two ways to use it:
 *
 *   Route::get('/legacy/{invoice}', ...)->middleware('rbac:billing.invoice.show,invoice');
 *   Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->middleware('rbac');
 *   // ...where the action carries #[RequiresPermission]
 */
final class EnsurePermission
{
    public function __construct(private readonly Rbac $rbac) {}

    public function handle(Request $request, Closure $next, ?string $permission = null, ?string $subject = null): Response
    {
        $checks = $permission !== null
            ? [[$permission, $subject]]
            : $this->fromAttributes($request);

        if ($checks === []) {
            throw new LogicException('The rbac middleware needs a permission argument or a #[RequiresPermission] attribute on the action.');
        }

        $user = $request->user();

        if (! $user instanceof Model) {
            throw new AuthenticationException;
        }

        foreach ($checks as [$name, $parameter]) {
            $record = null;

            if ($parameter !== null) {
                $record = $this->resolveRecord($request, $parameter);

                // Fail closed: a scoped check without a real record would be a list check.
                if (! $record instanceof Model) {
                    throw new LogicException("Route parameter [{$parameter}] must be bound to an Eloquent model for [{$name}].");
                }
            }

            $this->rbac->enforce($name, $record, $user);
        }

        return $next($request);
    }

    /**
     * This middleware can run before SubstituteBindings (for example outside the
     * web group), so bind the parameter here when it is still a raw value.
     */
    private function resolveRecord(Request $request, string $parameter): mixed
    {
        $route = $request->route();

        if ($route === null) {
            return null;
        }

        $record = $route->parameter($parameter);

        if (! $record instanceof Model) {
            // Same two steps as Laravel's SubstituteBindings middleware.
            $router = app('router');
            $router->substituteBindings($route);
            $router->substituteImplicitBindings($route);
            $record = $route->parameter($parameter);
        }

        return $record;
    }

    /**
     * @return list<array{0: string, 1: ?string}>
     */
    private function fromAttributes(Request $request): array
    {
        $route = $request->route();
        $action = $route?->getAction('controller');

        if (! is_string($action)) {
            return [];
        }

        [$class, $method] = Str::parseCallback($action, '__invoke');

        if (! class_exists($class)) {
            return [];
        }

        $reflections = [new ReflectionClass($class)];

        if (method_exists($class, $method)) {
            $reflections[] = new ReflectionMethod($class, $method);
        }

        $checks = [];

        foreach ($reflections as $reflection) {
            foreach ($reflection->getAttributes(RequiresPermission::class) as $attribute) {
                $required = $attribute->newInstance();
                $checks[] = [$required->permission, $required->subject];
            }
        }

        return $checks;
    }
}
