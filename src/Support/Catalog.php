<?php

namespace Siberfx\RbacPermission\Support;

use InvalidArgumentException;
use Siberfx\RbacPermission\Exceptions\InvalidPermission;

/**
 * The known universe of permissions, built from config('rbac.modules').
 */
final class Catalog
{
    /** @var list<string>|null */
    private ?array $keys = null;

    /** @var array<string, int> */
    private array $index = [];

    /**
     * @param  array<string, array{label?: string, resources: array<string, list<string>>}>  $modules
     * @param  list<string>  $verbs
     */
    public function __construct(
        private readonly array $modules,
        private readonly array $verbs,
    ) {}

    /**
     * @return array<string, array{label: string, resources: array<string, list<string>>}>
     */
    public function modules(): array
    {
        return $this->modules;
    }

    /**
     * @return list<string> every concrete key, e.g. "billing.invoice.edit"
     */
    public function permissions(): array
    {
        if ($this->keys !== null) {
            return $this->keys;
        }

        $keys = [];

        foreach ($this->modules as $module => $definition) {
            $this->assertSegment($module, 'module');

            foreach ($definition['resources'] ?? [] as $resource => $verbs) {
                $this->assertSegment($resource, "resource in module [{$module}]");

                foreach ($verbs as $verb) {
                    if (! in_array($verb, $this->verbs, true)) {
                        throw new InvalidArgumentException("Verb [{$verb}] of [{$module}.{$resource}] is not listed in rbac.verbs.");
                    }

                    $keys[] = "{$module}.{$resource}.{$verb}";
                }
            }
        }

        $this->keys = array_values(array_unique($keys));
        $this->index = array_flip($this->keys);

        return $this->keys;
    }

    public function has(string $key): bool
    {
        $this->permissions();

        return isset($this->index[$key]);
    }

    /**
     * @return list<string>
     */
    public function matching(string $pattern): array
    {
        return array_values(array_filter(
            $this->permissions(),
            fn (string $key) => PermissionKey::matches($pattern, $key),
        ));
    }

    public function assertGrantable(string $pattern): void
    {
        if ($this->matching($pattern) === []) {
            throw new InvalidPermission("Permission [{$pattern}] matches nothing in the rbac.modules catalog.");
        }
    }

    private function assertSegment(string $value, string $label): void
    {
        if (! preg_match('/^[a-z0-9_-]+$/', $value)) {
            throw new InvalidArgumentException("Invalid {$label} name [{$value}] in rbac.modules.");
        }
    }
}
