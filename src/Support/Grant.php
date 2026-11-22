<?php

namespace Siberfx\RbacPermission\Support;

/**
 * One effective grant, loaded from a role or from a direct user override.
 * $expiresAt is kept so a cached grant still stops applying on time.
 */
final readonly class Grant
{
    public const ALLOW = 'allow';

    public const DENY = 'deny';

    public function __construct(
        public string $permission,
        public string $scope,
        public string $effect,
        public string $source,
        public ?int $expiresAt = null,
    ) {}

    /**
     * True while the grant applies: it has no expiry, or the expiry (a Unix timestamp) is still ahead.
     */
    public function isActive(?int $now = null): bool
    {
        return $this->expiresAt === null || $this->expiresAt > ($now ?? time());
    }

    /**
     * Human readable form, e.g. "billing.invoice.edit@team" or "deny billing.invoice.delete@own".
     */
    public function label(): string
    {
        $grant = "{$this->permission}@{$this->scope}";

        return $this->effect === self::DENY ? "deny {$grant}" : $grant;
    }
}
