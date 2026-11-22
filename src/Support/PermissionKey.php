<?php

declare(strict_types=1);

namespace Siberfx\RbacPermission\Support;

use Siberfx\RbacPermission\Exceptions\InvalidPermission;

/**
 * The permission grammar:   module.resource.verb[@scope]
 *
 *   billing.invoice.edit@team   one permission, reaching team scope
 *
 *   billing.invoice.*@tenant    every invoice verb, across the tenant
 *   billing.*                   every billing permission (trailing wildcard)
 *
 *   *@global                    everything
 */
final class PermissionKey
{
    private const SEGMENT = '/^(\*|[a-z0-9_-]+)$/';

    private const SCOPE = '/^[a-z][a-z0-9_-]*$/';

    /**
     * @return array{0: string, 1: string} [pattern, scope]
     */
    public static function parse(string $input, string $defaultScope): array
    {
        $input = trim($input);
        $pattern = $input;
        $scope = $defaultScope;

        if (str_contains($input, '@')) {
            [$pattern, $scope] = explode('@', $input, 2);

            if (! preg_match(self::SCOPE, $scope)) {
                throw new InvalidPermission("Invalid scope in [{$input}].");
            }
        }

        if (! self::isPattern($pattern)) {
            throw new InvalidPermission("Invalid permission [{$pattern}]. Use module.resource.verb, wildcards (*) allowed.");
        }

        return [$pattern, $scope];
    }

    /**
     * A pattern has 1 to 3 dot-separated segments; each is a word or "*".
     */
    public static function isPattern(string $pattern): bool
    {
        $segments = explode('.', $pattern);

        if (count($segments) > 3) {
            return false;
        }

        foreach ($segments as $segment) {
            if (! preg_match(self::SEGMENT, $segment)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Does a grant pattern cover a concrete permission key?
     *
     * A trailing "*" covers the rest of the key, so "billing.*" matches
     * "billing.invoice.edit". A "*" in the middle matches exactly one segment.
     */
    public static function matches(string $pattern, string $key): bool
    {
        $patternSegments = explode('.', $pattern);
        $keySegments = explode('.', $key);
        $last = count($patternSegments) - 1;

        foreach ($patternSegments as $i => $segment) {
            if ($segment === '*' && $i === $last) {
                return true;
            }

            if (! isset($keySegments[$i])) {
                return false;
            }

            if ($segment !== '*' && $segment !== $keySegments[$i]) {
                return false;
            }
        }

        return count($patternSegments) === count($keySegments);
    }
}
