<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\TankerCompartment;
use Illuminate\Auth\Access\HandlesAuthorization;

class TankerCompartmentPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TankerCompartment');
    }

    public function view(AuthUser $authUser, TankerCompartment $tankerCompartment): bool
    {
        return $authUser->can('View:TankerCompartment');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TankerCompartment');
    }

    public function update(AuthUser $authUser, TankerCompartment $tankerCompartment): bool
    {
        return $authUser->can('Update:TankerCompartment');
    }

    public function delete(AuthUser $authUser, TankerCompartment $tankerCompartment): bool
    {
        return $authUser->can('Delete:TankerCompartment');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:TankerCompartment');
    }

    public function restore(AuthUser $authUser, TankerCompartment $tankerCompartment): bool
    {
        return $authUser->can('Restore:TankerCompartment');
    }

    public function forceDelete(AuthUser $authUser, TankerCompartment $tankerCompartment): bool
    {
        return $authUser->can('ForceDelete:TankerCompartment');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TankerCompartment');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TankerCompartment');
    }

    public function replicate(AuthUser $authUser, TankerCompartment $tankerCompartment): bool
    {
        return $authUser->can('Replicate:TankerCompartment');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:TankerCompartment');
    }

}