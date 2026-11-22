<?php

declare(strict_types=1);

namespace Siberfx\RbacPermission\Scopes;

use InvalidArgumentException;
use Siberfx\RbacPermission\Exceptions\InvalidPermission;

/**
 * The ordered scope ladder. Register custom scopes with register() or in config('rbac.scopes').
 */
final class ScopeRegistry
{
    /** @var array<string, array{level: int, resolver: class-string<ScopeContract>}> */
    private array $scopes = [];

    /** @var array<string, ScopeContract> */
    private array $instances = [];

    /**
     * @param  array<string, array{level: int, resolver: string}>  $scopes
     */
    public function __construct(array $scopes = [])
    {
        foreach ($scopes as $name => $definition) {
            $this->register($name, $definition['resolver'], (int) $definition['level']);
        }
    }

    /**
     * @param  string  $resolver  a class implementing ScopeContract (checked)
     */
    public function register(string $name, string $resolver, int $level): void
    {
        if (! preg_match('/^[a-z][a-z0-9_-]*$/', $name)) {
            throw new InvalidArgumentException("Invalid scope name [{$name}].");
        }

        if (! is_a($resolver, ScopeContract::class, true)) {
            throw new InvalidArgumentException("Scope [{$name}] resolver must implement ".ScopeContract::class.'.');
        }

        $this->scopes[$name] = ['level' => $level, 'resolver' => $resolver];
        unset($this->instances[$name]);

        uasort($this->scopes, fn (array $a, array $b) => $a['level'] <=> $b['level']);
    }

    public function has(string $name): bool
    {
        return isset($this->scopes[$name]);
    }

    public function get(string $name): ?ScopeContract
    {
        if (! $this->has($name)) {
            return null;
        }

        return $this->instances[$name] ??= app($this->scopes[$name]['resolver']);
    }

    /**
     * Unknown scopes rank as 0 so they never win a "widest" comparison.
     */
    public function level(string $name): int
    {
        return $this->scopes[$name]['level'] ?? 0;
    }

    /**
     * @return list<string> scope names, narrowest first
     */
    public function names(): array
    {
        return array_keys($this->scopes);
    }

    public function assertKnown(string $name): void
    {
        if (! $this->has($name)) {
            throw new InvalidPermission("Unknown scope [{$name}]. Known: ".implode(', ', $this->names()).'.');
        }
    }
}
