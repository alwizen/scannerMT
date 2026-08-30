<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ScanLog;
use Illuminate\Auth\Access\HandlesAuthorization;

class ScanLogPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ScanLog');
    }

    public function view(AuthUser $authUser, ScanLog $scanLog): bool
    {
        return $authUser->can('View:ScanLog');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ScanLog');
    }

    public function update(AuthUser $authUser, ScanLog $scanLog): bool
    {
        return $authUser->can('Update:ScanLog');
    }

    public function delete(AuthUser $authUser, ScanLog $scanLog): bool
    {
        return $authUser->can('Delete:ScanLog');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ScanLog');
    }

    public function restore(AuthUser $authUser, ScanLog $scanLog): bool
    {
        return $authUser->can('Restore:ScanLog');
    }

    public function forceDelete(AuthUser $authUser, ScanLog $scanLog): bool
    {
        return $authUser->can('ForceDelete:ScanLog');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ScanLog');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ScanLog');
    }

    public function replicate(AuthUser $authUser, ScanLog $scanLog): bool
    {
        return $authUser->can('Replicate:ScanLog');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ScanLog');
    }

}