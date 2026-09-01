<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ParkingLocation;
use Illuminate\Auth\Access\HandlesAuthorization;

class ParkingLocationPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ParkingLocation');
    }

    public function view(AuthUser $authUser, ParkingLocation $parkingLocation): bool
    {
        return $authUser->can('View:ParkingLocation');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ParkingLocation');
    }

    public function update(AuthUser $authUser, ParkingLocation $parkingLocation): bool
    {
        return $authUser->can('Update:ParkingLocation');
    }

    public function delete(AuthUser $authUser, ParkingLocation $parkingLocation): bool
    {
        return $authUser->can('Delete:ParkingLocation');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ParkingLocation');
    }

    public function restore(AuthUser $authUser, ParkingLocation $parkingLocation): bool
    {
        return $authUser->can('Restore:ParkingLocation');
    }

    public function forceDelete(AuthUser $authUser, ParkingLocation $parkingLocation): bool
    {
        return $authUser->can('ForceDelete:ParkingLocation');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ParkingLocation');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ParkingLocation');
    }

    public function replicate(AuthUser $authUser, ParkingLocation $parkingLocation): bool
    {
        return $authUser->can('Replicate:ParkingLocation');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ParkingLocation');
    }

}