<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Siberfx\RbacPermission\Http\Controllers\RbacAdminController;

Route::prefix((string) config('rbac.ui.prefix', 'rbac'))
    ->middleware((array) config('rbac.ui.middleware', ['web', 'auth']))
    ->name('rbac.')
    ->group(function () {
        Route::get('roles', [RbacAdminController::class, 'roles'])->name('roles.index');
        Route::get('roles/{role}', [RbacAdminController::class, 'role'])->name('roles.show');
        Route::post('roles/{role}/grants', [RbacAdminController::class, 'addGrant'])->name('roles.grants.store');
        Route::delete('roles/{role}/grants', [RbacAdminController::class, 'removeGrant'])->name('roles.grants.destroy');

        Route::get('users/{id}', [RbacAdminController::class, 'user'])->name('users.show');
        Route::post('users/{id}/roles', [RbacAdminController::class, 'assign'])->name('users.roles.store');
        Route::delete('users/{id}/roles/{slug}', [RbacAdminController::class, 'unassign'])->name('users.roles.destroy');
    });
