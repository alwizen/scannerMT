<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Tanker;
use Illuminate\Auth\Access\HandlesAuthorization;

class TankerPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Tanker');
    }

    public function view(AuthUser $authUser, Tanker $tanker): bool
    {
        return $authUser->can('View:Tanker');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Tanker');
    }

    public function update(AuthUser $authUser, Tanker $tanker): bool
    {
        return $authUser->can('Update:Tanker');
    }

    public function delete(AuthUser $authUser, Tanker $tanker): bool
    {
        return $authUser->can('Delete:Tanker');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Tanker');
    }

    public function restore(AuthUser $authUser, Tanker $tanker): bool
    {
        return $authUser->can('Restore:Tanker');
    }

    public function forceDelete(AuthUser $authUser, Tanker $tanker): bool
    {
        return $authUser->can('ForceDelete:Tanker');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Tanker');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Tanker');
    }

    public function replicate(AuthUser $authUser, Tanker $tanker): bool
    {
        return $authUser->can('Replicate:Tanker');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Tanker');
    }

}