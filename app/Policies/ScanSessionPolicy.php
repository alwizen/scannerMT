<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ScanSession;
use Illuminate\Auth\Access\HandlesAuthorization;

class ScanSessionPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ScanSession');
    }

    public function view(AuthUser $authUser, ScanSession $scanSession): bool
    {
        return $authUser->can('View:ScanSession');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ScanSession');
    }

    public function update(AuthUser $authUser, ScanSession $scanSession): bool
    {
        return $authUser->can('Update:ScanSession');
    }

    public function delete(AuthUser $authUser, ScanSession $scanSession): bool
    {
        return $authUser->can('Delete:ScanSession');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ScanSession');
    }

    public function restore(AuthUser $authUser, ScanSession $scanSession): bool
    {
        return $authUser->can('Restore:ScanSession');
    }

    public function forceDelete(AuthUser $authUser, ScanSession $scanSession): bool
    {
        return $authUser->can('ForceDelete:ScanSession');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ScanSession');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ScanSession');
    }

    public function replicate(AuthUser $authUser, ScanSession $scanSession): bool
    {
        return $authUser->can('Replicate:ScanSession');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ScanSession');
    }

}