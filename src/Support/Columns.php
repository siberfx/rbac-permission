<?php

namespace Siberfx\RbacPermission\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Reads the owner / team / tenant of a model. A model can override the column
 * by defining rbacOwnerColumn(), rbacTeamColumn() or rbacTenantColumn()
 * (the HasRbacScopes trait provides these defaults).
 */
final class Columns
{
    public static function name(Model $model, string $kind): string
    {
        $method = 'rbac'.ucfirst($kind).'Column';

        return method_exists($model, $method)
            ? (string) $model->{$method}()
            : (string) config("rbac.columns.{$kind}");
    }

    public static function key(Model $model, string $kind): mixed
    {
        return $model->getAttribute(self::name($model, $kind));
    }

    /**
     * @return list<string>
     */
    public static function teamIds(Model $user): array
    {
        return method_exists($user, 'rbacTeamIds')
            ? array_map('strval', $user->rbacTeamIds())
            : [];
    }
}
