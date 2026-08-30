<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Driver;
use Illuminate\Auth\Access\HandlesAuthorization;

class DriverPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Driver');
    }

    public function view(AuthUser $authUser, Driver $driver): bool
    {
        return $authUser->can('View:Driver');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Driver');
    }

    public function update(AuthUser $authUser, Driver $driver): bool
    {
        return $authUser->can('Update:Driver');
    }

    public function delete(AuthUser $authUser, Driver $driver): bool
    {
        return $authUser->can('Delete:Driver');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Driver');
    }

    public function restore(AuthUser $authUser, Driver $driver): bool
    {
        return $authUser->can('Restore:Driver');
    }

    public function forceDelete(AuthUser $authUser, Driver $driver): bool
    {
        return $authUser->can('ForceDelete:Driver');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Driver');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Driver');
    }

    public function replicate(AuthUser $authUser, Driver $driver): bool
    {
        return $authUser->can('Replicate:Driver');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Driver');
    }

}