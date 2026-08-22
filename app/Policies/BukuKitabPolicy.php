<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\BukuKitab;
use Illuminate\Auth\Access\HandlesAuthorization;

class BukuKitabPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:BukuKitab');
    }

    public function view(AuthUser $authUser, BukuKitab $bukuKitab): bool
    {
        return $authUser->can('View:BukuKitab');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:BukuKitab');
    }

    public function update(AuthUser $authUser, BukuKitab $bukuKitab): bool
    {
        return $authUser->can('Update:BukuKitab');
    }

    public function delete(AuthUser $authUser, BukuKitab $bukuKitab): bool
    {
        return $authUser->can('Delete:BukuKitab');
    }

    public function restore(AuthUser $authUser, BukuKitab $bukuKitab): bool
    {
        return $authUser->can('Restore:BukuKitab');
    }

    public function forceDelete(AuthUser $authUser, BukuKitab $bukuKitab): bool
    {
        return $authUser->can('ForceDelete:BukuKitab');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:BukuKitab');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:BukuKitab');
    }

    public function replicate(AuthUser $authUser, BukuKitab $bukuKitab): bool
    {
        return $authUser->can('Replicate:BukuKitab');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:BukuKitab');
    }

}