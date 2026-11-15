<?php

namespace Siberfx\RbacPermission\Listeners;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Siberfx\RbacPermission\Events\RbacChanged;

/**
 * Stores RbacChanged events in the audit table. Does nothing unless rbac.audit.enabled is true.
 */
class WriteAuditLog
{
    public function handle(RbacChanged $event): void
    {
        if (! config('rbac.audit.enabled', false)) {
            return;
        }

        $details = $event->details;
        $expiresAt = $details['expires_at'] ?? null;

        if ($expiresAt instanceof DateTimeInterface) {
            $details['expires_at'] = $expiresAt->format('Y-m-d H:i:s');
        }

        DB::table(config('rbac.tables.audit_logs', 'rbac_audit_logs'))->insert([
            'action' => $event->action,
            'actor_type' => $event->actor?->getMorphClass(),
            'actor_id' => $event->actor ? (string) $event->actor->getKey() : null,
            'subject_type' => $event->subject?->getMorphClass(),
            'subject_id' => $event->subject ? (string) $event->subject->getKey() : null,
            'permission' => $this->string($details['permission'] ?? null),
            'scope' => $this->string($details['scope'] ?? null),
            'effect' => $this->string($details['effect'] ?? null),
            'expires_at' => $expiresAt instanceof DateTimeInterface ? $expiresAt : null,
            'properties' => $details === [] ? null : json_encode($details, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }

    private function string(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
