<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\KelompokMappingAkun;
use Illuminate\Auth\Access\HandlesAuthorization;

class KelompokMappingAkunPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:KelompokMappingAkun');
    }

    public function view(AuthUser $authUser, KelompokMappingAkun $kelompokMappingAkun): bool
    {
        return $authUser->can('View:KelompokMappingAkun');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:KelompokMappingAkun');
    }

    public function update(AuthUser $authUser, KelompokMappingAkun $kelompokMappingAkun): bool
    {
        return $authUser->can('Update:KelompokMappingAkun');
    }

    public function delete(AuthUser $authUser, KelompokMappingAkun $kelompokMappingAkun): bool
    {
        return $authUser->can('Delete:KelompokMappingAkun');
    }

    public function restore(AuthUser $authUser, KelompokMappingAkun $kelompokMappingAkun): bool
    {
        return $authUser->can('Restore:KelompokMappingAkun');
    }

    public function forceDelete(AuthUser $authUser, KelompokMappingAkun $kelompokMappingAkun): bool
    {
        return $authUser->can('ForceDelete:KelompokMappingAkun');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:KelompokMappingAkun');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:KelompokMappingAkun');
    }

    public function replicate(AuthUser $authUser, KelompokMappingAkun $kelompokMappingAkun): bool
    {
        return $authUser->can('Replicate:KelompokMappingAkun');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:KelompokMappingAkun');
    }

}