<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PurchaseOrder;
use Illuminate\Auth\Access\HandlesAuthorization;

class PurchaseOrderPolicy
{
    use HandlesAuthorization;

    public function before(AuthUser $authUser, string $ability): ?bool
    {
        if (method_exists($authUser, 'hasRole') && $authUser->hasRole('super_admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PurchaseOrder') || true;
    }

    public function view(AuthUser $authUser, PurchaseOrder $purchaseOrder): bool
    {
        return $authUser->can('View:PurchaseOrder') || true;
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PurchaseOrder') || true;
    }

    public function update(AuthUser $authUser, PurchaseOrder $purchaseOrder): bool
    {
        return $authUser->can('Update:PurchaseOrder') || true;
    }

    public function delete(AuthUser $authUser, PurchaseOrder $purchaseOrder): bool
    {
        return $authUser->can('Delete:PurchaseOrder') || true;
    }

    public function restore(AuthUser $authUser, PurchaseOrder $purchaseOrder): bool
    {
        return $authUser->can('Restore:PurchaseOrder') || true;
    }

    public function forceDelete(AuthUser $authUser, PurchaseOrder $purchaseOrder): bool
    {
        return $authUser->can('ForceDelete:PurchaseOrder') || true;
    }
}
