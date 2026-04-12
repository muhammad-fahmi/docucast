<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Base Policy class implementing standard CRUD policies
 *
 * All policies should extend this class to ensure consistent authorization behavior
 * and reduce code duplication (ISP - Interface Segregation Principle)
 */
abstract class BaseResourcePolicy
{
    /**
     * Get the model class name for permission checking
     * Override this method in child policies to customize the permission base name
     */
    abstract protected function modelName(): string;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can($this->permissionName('ViewAny'));
    }

    public function view(AuthUser $authUser, Model $model): bool
    {
        return $authUser->can($this->permissionName('View'));
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can($this->permissionName('Create'));
    }

    public function update(AuthUser $authUser, Model $model): bool
    {
        return $authUser->can($this->permissionName('Update'));
    }

    public function delete(AuthUser $authUser, Model $model): bool
    {
        return $authUser->can($this->permissionName('Delete'));
    }

    public function restore(AuthUser $authUser, Model $model): bool
    {
        return $authUser->can($this->permissionName('Restore'));
    }

    public function forceDelete(AuthUser $authUser, Model $model): bool
    {
        return $authUser->can($this->permissionName('ForceDelete'));
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can($this->permissionName('ForceDeleteAny'));
    }

    /**
     * Build permission name from action and model name
     */
    protected function permissionName(string $action): string
    {
        return "{$action}:{$this->modelName()}";
    }
}
